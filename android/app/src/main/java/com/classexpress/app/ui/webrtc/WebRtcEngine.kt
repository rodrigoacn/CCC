package com.classexpress.app.ui.webrtc

import android.content.Context
import android.util.Log
import com.classexpress.app.model.SignalsResponse
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import org.webrtc.AudioSource
import org.webrtc.AudioTrack
import org.webrtc.Camera1Enumerator
import org.webrtc.Camera2Enumerator
import org.webrtc.CameraEnumerator
import org.webrtc.DataChannel
import org.webrtc.DefaultVideoDecoderFactory
import org.webrtc.DefaultVideoEncoderFactory
import org.webrtc.EglBase
import org.webrtc.IceCandidate
import org.webrtc.MediaConstraints
import org.webrtc.MediaStream
import org.webrtc.PeerConnection
import org.webrtc.PeerConnectionFactory
import org.webrtc.RendererCommon
import org.webrtc.RtpReceiver
import org.webrtc.RtpTransceiver
import org.webrtc.SdpObserver
import org.webrtc.SessionDescription
import org.webrtc.SurfaceTextureHelper
import org.webrtc.SurfaceViewRenderer
import org.webrtc.VideoCapturer
import org.webrtc.VideoSource
import org.webrtc.VideoTrack
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

/**
 * Cliente WebRTC mínimo (cámara + micrófono) para una clase en vivo.
 * La señalización viaja por HTTP polling usando `signal` / `poll_signals`
 * de api_mobile.php (igual que la web con su RedisPoll).
 */
class WebRtcEngine(
    private val context: Context,
    private val role: Role,
    private val scope: CoroutineScope,
    private val channel: SignalChannel,
) {

    enum class Role { HOST, GUEST }

    interface SignalChannel {
        suspend fun send(tipo: String, payload: String)
        suspend fun poll(afterId: Long): SignalsResponse
    }

    val localRenderer: SurfaceViewRenderer by lazy {
        SurfaceViewRenderer(context).apply { setMirror(role == Role.HOST) }
    }
    val remoteRenderer: SurfaceViewRenderer by lazy {
        SurfaceViewRenderer(context).apply { setMirror(false) }
    }

    private var eglBase: EglBase? = null
    private var factory: PeerConnectionFactory? = null
    private var surfaceTextureHelper: SurfaceTextureHelper? = null
    private var videoCapturer: VideoCapturer? = null
    private var videoTrack: VideoTrack? = null
    private var audioTrack: AudioTrack? = null
    private var peer: PeerConnection? = null
    private var pollJob: Job? = null
    private var lastSignalId = 0L
    private var disposed = false

    private val iceServers = listOf(
        PeerConnection.IceServer.builder("stun:stun.l.google.com:19302").createIceServer(),
        PeerConnection.IceServer.builder("stun:stun1.l.google.com:19302").createIceServer(),
    )

    fun start() {
        if (disposed) return
        val egl = EglBase.create()
        eglBase = egl
        localRenderer.init(egl.eglBaseContext, null)
        localRenderer.setScalingType(RendererCommon.ScalingType.SCALE_ASPECT_FIT)
        remoteRenderer.init(egl.eglBaseContext, null)
        remoteRenderer.setScalingType(RendererCommon.ScalingType.SCALE_ASPECT_FIT)

        ensureFactoryInitialized()

        val f = PeerConnectionFactory.builder()
            .setVideoEncoderFactory(DefaultVideoEncoderFactory(egl.eglBaseContext, true, true))
            .setVideoDecoderFactory(DefaultVideoDecoderFactory(egl.eglBaseContext))
            .createPeerConnectionFactory()
        factory = f

        val sth = SurfaceTextureHelper.create("CE_CapturerThread", egl.eglBaseContext)
        surfaceTextureHelper = sth

        val videoSource = f.createVideoSource(false)
        createVideoCapturer()?.let { capturer ->
            videoCapturer = capturer
            capturer.initialize(sth, context, videoSource.capturerObserver)
            capturer.startCapture(640, 480, 30)
            val track = f.createVideoTrack("ce_video_local", videoSource)
            videoTrack = track
            track.addSink(localRenderer)
        }

        val audioSource = f.createAudioSource(MediaConstraints())
        audioTrack = f.createAudioTrack("ce_audio_local", audioSource)
    }

    /** Arranca la señalización: el HOST envía su offer y ambos entran al polling. */
    fun startSignaling() {
        scope.launch {
            try {
                if (role == Role.HOST) {
                    val offer = createOffer()
                    channel.send("offer", offer)
                }
                runPolling()
            } catch (e: Exception) {
                Log.e(TAG, "Error de señalización", e)
            }
        }
    }

    fun stop() {
        pollJob?.cancel()
        try {
            videoCapturer?.stopCapture()
        } catch (_: Exception) {
        }
        peer?.close()
        peer = null
    }

    fun dispose() {
        if (disposed) return
        disposed = true
        stop()
        try {
            videoTrack?.dispose()
            audioTrack?.dispose()
            surfaceTextureHelper?.dispose()
            remoteRenderer.release()
            localRenderer.release()
            factory?.dispose()
            eglBase?.release()
        } catch (_: Exception) {
        }
    }

    private suspend fun createOffer(): String = suspendCancellableCoroutine { cont ->
        val pc = createPeer()
        peer = pc
        val constraints = MediaConstraints().apply {
            mandatory.add(MediaConstraints.KeyValuePair("OfferToReceiveVideo", "true"))
            mandatory.add(MediaConstraints.KeyValuePair("OfferToReceiveAudio", "true"))
        }
        pc.createOffer(object : SdpObserver {
            override fun onCreateSuccess(desc: SessionDescription) {
                val sdp = desc.description
                pc.setLocalDescription(object : SdpObserver {
                    override fun onCreateSuccess(desc: SessionDescription) {
                    }

                    override fun onSetSuccess() {
                        cont.resume(sdp)
                    }

                    override fun onSetFailure(error: String?) {
                        cont.resumeWithException(RuntimeException(error ?: "setLocalDescription falló"))
                    }

                    override fun onCreateFailure(error: String?) {
                        cont.resumeWithException(RuntimeException(error ?: "setLocalDescription falló"))
                    }
                }, desc)
            }

            override fun onSetSuccess() {
            }

            override fun onSetFailure(error: String?) {
                cont.resumeWithException(RuntimeException(error ?: "createOffer falló"))
            }

            override fun onCreateFailure(error: String?) {
                cont.resumeWithException(RuntimeException(error ?: "createOffer falló"))
            }
        }, constraints)
    }

    /** HOST: recibe el answer y lo aplica. */
    private suspend fun handleRemoteSdp(sdp: String): Unit = suspendCancellableCoroutine { cont ->
        val pc = peer ?: run { cont.resume(Unit); return@suspendCancellableCoroutine }
        val desc = SessionDescription(SessionDescription.Type.ANSWER, sdp)
        pc.setRemoteDescription(remoteObserver(cont), desc)
    }

    /** GUEST: recibe el offer, responde con un answer y lo envía. */
    private suspend fun receiveOfferAndAnswer(offerSdp: String): String = suspendCancellableCoroutine { cont ->
        val pc = createPeer()
        peer = pc
        val offer = SessionDescription(SessionDescription.Type.OFFER, offerSdp)
        pc.setRemoteDescription(object : SdpObserver {
            override fun onCreateSuccess(desc: SessionDescription) {
            }

            override fun onSetSuccess() {
                val constraints = MediaConstraints().apply {
                    mandatory.add(MediaConstraints.KeyValuePair("OfferToReceiveVideo", "true"))
                    mandatory.add(MediaConstraints.KeyValuePair("OfferToReceiveAudio", "true"))
                }
                pc.createAnswer(object : SdpObserver {
                    override fun onCreateSuccess(desc: SessionDescription) {
                        val sdp = desc.description
                        pc.setLocalDescription(object : SdpObserver {
                            override fun onCreateSuccess(desc: SessionDescription) {
                            }

                            override fun onSetSuccess() {
                                cont.resume(sdp)
                            }

                            override fun onSetFailure(error: String?) {
                                cont.resumeWithException(RuntimeException(error ?: "setLocalDescription falló"))
                            }

                            override fun onCreateFailure(error: String?) {
                                cont.resumeWithException(RuntimeException(error ?: "setLocalDescription falló"))
                            }
                        }, desc)
                    }

                    override fun onSetSuccess() {
                    }

                    override fun onSetFailure(error: String?) {
                        cont.resumeWithException(RuntimeException(error ?: "setLocalDescription falló"))
                    }

                    override fun onCreateFailure(error: String?) {
                        cont.resumeWithException(RuntimeException(error ?: "createAnswer falló"))
                    }
                }, constraints)
            }

            override fun onSetFailure(error: String?) {
                cont.resumeWithException(RuntimeException(error ?: "setRemoteDescription falló"))
            }

            override fun onCreateFailure(error: String?) {
                cont.resumeWithException(RuntimeException(error ?: "setRemoteDescription falló"))
            }
        }, offer)
    }

    private fun addIceCandidate(payload: String) {
        val parts = payload.split("|", limit = 3)
        if (parts.size < 3) return
        val candidate = try {
            IceCandidate(parts[0], parts[1].toInt(), parts[2])
        } catch (_: Exception) {
            return
        }
        peer?.addIceCandidate(candidate)
    }

    private fun runPolling() {
        pollJob?.cancel()
        pollJob = scope.launch {
            while (isActive) {
                try {
                    val res = channel.poll(lastSignalId)
                    for (sig in res.signals) {
                        if (sig.signalId > lastSignalId) lastSignalId = sig.signalId
                        when (sig.tipo) {
                            "offer" -> if (role == Role.GUEST) {
                                val answer = receiveOfferAndAnswer(sig.payload)
                                channel.send("answer", answer)
                            }

                            "answer" -> if (role == Role.HOST) handleRemoteSdp(sig.payload)
                            "candidate" -> addIceCandidate(sig.payload)
                            "bye" -> stop()
                        }
                    }
                } catch (_: Exception) {
                }
                delay(1500)
            }
        }
    }

    private fun createPeer(): PeerConnection {
        val config = PeerConnection.RTCConfiguration(iceServers)
        val pc = factory!!.createPeerConnection(config, object : PeerConnection.Observer {
            override fun onSignalingChange(signalingState: PeerConnection.SignalingState) {
            }

            override fun onIceConnectionChange(iceConnectionState: PeerConnection.IceConnectionState) {
            }

            override fun onIceConnectionReceivingChange(receiving: Boolean) {
            }

            override fun onIceGatheringChange(iceGatheringState: PeerConnection.IceGatheringState) {
            }

            override fun onIceCandidate(candidate: IceCandidate) {
                val payload = "${candidate.sdpMid ?: ""}|${candidate.sdpMLineIndex}|${candidate.sdp}"
                scope.launch {
                    try {
                        channel.send("candidate", payload)
                    } catch (_: Exception) {
                    }
                }
            }

            override fun onIceCandidatesRemoved(candidates: Array<IceCandidate>) {
            }

            @Suppress("DEPRECATION")
            override fun onAddStream(mediaStream: MediaStream) {
            }

            @Suppress("DEPRECATION")
            override fun onRemoveStream(mediaStream: MediaStream) {
            }

            override fun onDataChannel(dataChannel: DataChannel) {
            }

            override fun onRenegotiationNeeded() {
            }

            @Suppress("DEPRECATION")
            override fun onAddTrack(receiver: RtpReceiver, mediaStreams: Array<MediaStream>) {
            }

            override fun onTrack(transceiver: RtpTransceiver) {
                val track = transceiver.receiver.track()
                if (track is VideoTrack) {
                    track.addSink(remoteRenderer)
                }
            }

            @Suppress("DEPRECATION")
            override fun onRemoveTrack(receiver: RtpReceiver) {
            }
        }) ?: error("No se pudo crear la conexión WebRTC")

        videoTrack?.let { pc.addTrack(it, listOf("ce_stream")) }
        audioTrack?.let { pc.addTrack(it, listOf("ce_stream")) }
        return pc
    }

    private fun createVideoCapturer(): VideoCapturer? {
        return if (Camera2Enumerator.isSupported(context)) {
            createCameraCapturer(Camera2Enumerator(context))
        } else {
            createCameraCapturer(Camera1Enumerator(false))
        }
    }

    private fun createCameraCapturer(enumerator: CameraEnumerator): VideoCapturer? {
        val names = enumerator.deviceNames
        for (id in names) {
            if (enumerator.isFrontFacing(id)) return enumerator.createCapturer(id, null)
        }
        if (names.isNotEmpty()) return enumerator.createCapturer(names[0], null)
        return null
    }

    private fun remoteObserver(cont: kotlin.coroutines.Continuation<Unit>): SdpObserver = object : SdpObserver {
        override fun onCreateSuccess(desc: SessionDescription) {
        }

        override fun onSetSuccess() {
            cont.resume(Unit)
        }

        override fun onSetFailure(error: String?) {
            cont.resumeWithException(RuntimeException(error ?: "setRemoteDescription falló"))
        }

        override fun onCreateFailure(error: String?) {
            cont.resumeWithException(RuntimeException(error ?: "setRemoteDescription falló"))
        }
    }

    private fun ensureFactoryInitialized() {
        synchronized(initLock) {
            if (initialized) return
            try {
                PeerConnectionFactory.initialize(
                    PeerConnectionFactory.InitializationOptions.builder(context)
                        .createInitializationOptions()
                )
            } catch (_: Throwable) {
            }
            initialized = true
        }
    }

    companion object {
        private const val TAG = "WebRtcEngine"
        private val initLock = Any()
        private var initialized = false
    }
}
