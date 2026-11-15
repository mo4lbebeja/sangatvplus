package com.sangatv.plus.data.remote

import com.sangatv.plus.BuildConfig
import kotlinx.serialization.json.Json
import okhttp3.CertificatePinner
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

/**
 * HTTP client for the SangaTV Plus API.
 *
 * Release builds: base URL must be HTTPS and the server certificate is pinned
 * (BuildConfig.CERT_PINS, set at build time; the Gradle build refuses a release
 * without them). Trust in the hotel's internal CA comes from network_security_config.
 */
class ApiClient(tokenProvider: () -> String?, hotelProvider: () -> String? = { null }) {

    val json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
    }

    val baseUrl: String = BuildConfig.API_BASE_URL.let { if (it.endsWith("/")) it else "$it/" }

    init {
        if (BuildConfig.BUILD_TYPE == "release") {
            require(baseUrl.startsWith("https://")) { "Release builds require an HTTPS API URL" }
        }
    }

    private val pinner: CertificatePinner? = BuildConfig.CERT_PINS
        .split(',').map { it.trim() }.filter { it.startsWith("sha256/") }
        .takeIf { it.isNotEmpty() }
        ?.let { pins ->
            val host = baseUrl.toHttpUrl().host
            CertificatePinner.Builder().apply { pins.forEach { add(host, it) } }.build()
        }

    private val auth = Interceptor { chain ->
        val req = chain.request().newBuilder().header("Accept", "application/json")
        // Only attach the device token to our own API host.
        if (chain.request().url.host == baseUrl.toHttpUrl().host) {
            tokenProvider()?.let { req.header("Authorization", "Bearer $it") }
            // Multi-hotel: which hotel this TV belongs to (absent on single-hotel servers / before registration).
            hotelProvider()?.let { req.header("X-SangaTV-Hotel", it) }
        }
        chain.proceed(req.build())
    }

    val http: OkHttpClient = OkHttpClient.Builder()
        .connectTimeout(8, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .callTimeout(60, TimeUnit.SECONDS)
        .addInterceptor(auth)
        .apply { pinner?.let { certificatePinner(it) } }
        .build()

    val isPinned: Boolean get() = pinner != null

    val api: ApiService = Retrofit.Builder()
        .baseUrl(baseUrl)
        .client(http)
        .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
        .build()
        .create(ApiService::class.java)
}
