package com.classexpress.app.ui.webrtc

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.viewinterop.AndroidView
import org.webrtc.SurfaceViewRenderer

/** Encapsula un SurfaceViewRenderer de WebRTC dentro de Compose. */
@Composable
fun VideoSurface(renderer: SurfaceViewRenderer, modifier: Modifier = Modifier) {
    AndroidView(
        factory = { renderer },
        modifier = modifier.background(Color.Black),
    )
}
