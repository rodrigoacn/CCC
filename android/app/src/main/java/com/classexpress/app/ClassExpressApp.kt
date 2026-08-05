package com.classexpress.app

import android.app.Application
import android.content.Context
import com.classexpress.app.data.ApiService
import com.classexpress.app.data.SessionManager
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonDeserializer
import com.google.gson.JsonElement
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit
import java.lang.reflect.Type

class ClassExpressApp : Application() {
    override fun onCreate() {
        super.onCreate()
        Container.init(this)
    }
}

/** MySQL entrega TINYINT(1) como 0/1; Gson solo acepta true/false sin esto. */
private val booleanDeserializer = JsonDeserializer<Boolean> { json, _, _ ->
    when {
        json.isJsonPrimitive && json.asJsonPrimitive.isBoolean -> json.asBoolean
        json.isJsonPrimitive && json.asJsonPrimitive.isNumber -> json.asInt != 0
        json.isJsonPrimitive -> json.asString == "1" || json.asString.equals("true", ignoreCase = true)
        else -> false
    }
}

/**
 * Contenedor simple de dependencias (sin Dagger/Koin para mantener el proyecto ligero).
 */
object Container {
    lateinit var app: Application
        private set
    lateinit var session: SessionManager
        private set
    lateinit var api: ApiService
        private set
    val gson: Gson = GsonBuilder()
        .registerTypeAdapter(Boolean::class.java, booleanDeserializer)
        .registerTypeAdapter(java.lang.Boolean::class.java, booleanDeserializer)
        .create()

    fun init(context: Context) {
        app = context.applicationContext as Application
        session = SessionManager(app)

        val authInterceptor = okhttp3.Interceptor { chain ->
            val request = chain.request().newBuilder().apply {
                val token = session.tokenValue
                if (token.isNotEmpty()) header("Authorization", "Bearer $token")
            }.build()
            chain.proceed(request)
        }

        val logging = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC }

        val client = OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(logging)
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(40, TimeUnit.SECONDS)
            .writeTimeout(40, TimeUnit.SECONDS)
            .build()

        api = Retrofit.Builder()
            .baseUrl(Config.BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
            .create(ApiService::class.java)
    }
}
