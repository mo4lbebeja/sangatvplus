package com.sangatv.plus.data.remote

import com.sangatv.plus.data.model.AckRequest
import com.sangatv.plus.data.model.Bootstrap
import com.sangatv.plus.data.model.ChannelsResponse
import com.sangatv.plus.data.model.FacilitiesResponse
import com.sangatv.plus.data.model.HeartbeatRequest
import com.sangatv.plus.data.model.HeartbeatResponse
import com.sangatv.plus.data.model.OfflineAttempts
import com.sangatv.plus.data.model.PinVerifyRequest
import com.sangatv.plus.data.model.PinVerifyResponse
import com.sangatv.plus.data.model.ProvisionRequest
import com.sangatv.plus.data.model.ProvisionResponse
import com.sangatv.plus.data.model.TechnicianEvent
import com.sangatv.plus.data.model.UpdateResponse
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.Streaming
import retrofit2.http.Url

interface ApiService {
    @POST("api/v1/devices/provision")
    suspend fun provision(@Body body: ProvisionRequest): ProvisionResponse

    @GET("api/v1/bootstrap")
    suspend fun bootstrap(): Bootstrap

    @GET("api/v1/channels")
    suspend fun channels(): ChannelsResponse

    @GET("api/v1/facilities")
    suspend fun facilities(): FacilitiesResponse

    @POST("api/v1/heartbeat")
    suspend fun heartbeat(@Body body: HeartbeatRequest): HeartbeatResponse

    @POST("api/v1/commands/{id}/ack")
    suspend fun ack(@Path("id") id: Long, @Body body: AckRequest)

    @POST("api/v1/technician/verify")
    suspend fun verifyPin(@Body body: PinVerifyRequest): Response<PinVerifyResponse>

    @POST("api/v1/technician/offline-attempts")
    suspend fun reportOfflineAttempts(@Body body: OfflineAttempts)

    @POST("api/v1/technician/events")
    suspend fun technicianEvent(@Body body: TechnicianEvent)

    @GET("api/v1/app/update")
    suspend fun checkUpdate(@Query("version_code") versionCode: Long): UpdateResponse

    @Streaming
    @GET
    suspend fun download(@Url url: String): ResponseBody
}
