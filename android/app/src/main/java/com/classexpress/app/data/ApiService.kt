package com.classexpress.app.data

import com.google.gson.JsonObject
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query
import retrofit2.http.QueryMap

/**
 * Interfaz única contra `api_mobile.php`.
 * La API es un switch por `?action=...` y acepta JSON en el body (POST) o params (GET).
 */
interface ApiService {

    @POST("api_mobile.php")
    suspend fun post(
        @Query("action") action: String,
        @Body body: Map<String, @JvmSuppressWildcards Any?>,
    ): JsonObject

    @GET("api_mobile.php")
    suspend fun get(
        @Query("action") action: String,
        @QueryMap params: Map<String, String> = emptyMap(),
    ): JsonObject
}
