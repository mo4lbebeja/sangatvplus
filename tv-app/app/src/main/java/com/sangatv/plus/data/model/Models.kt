package com.sangatv.plus.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

// ---- Contract with the Laravel API (/api/v1). Field names follow the server. ----

@Serializable
data class ProvisionRequest(
    val code: String,
    @SerialName("device_uid") val deviceUid: String,
    val model: String?,
    @SerialName("android_version") val androidVersion: String?,
    @SerialName("app_version") val appVersion: String?,
    @SerialName("app_version_code") val appVersionCode: Long?,
    @SerialName("device_owner") val deviceOwner: Boolean,
)

@Serializable
data class ProvisionResponse(val token: String, val device: ProvisionedDevice)

@Serializable
data class ProvisionedDevice(val id: Long, val room: String)

@Serializable
data class Bootstrap(
    @SerialName("server_time") val serverTime: String,
    val hotel: Hotel,
    val room: RoomInfo,
    val guest: GuestInfo? = null,
    val slides: List<SlideDto> = emptyList(),
    val apps: List<AppDto> = emptyList(),
    /** Slide image chosen in the CMS as the "Aplikasi" screen background; null = plain theme. */
    @SerialName("apps_background_url") val appsBackgroundUrl: String? = null,
    @SerialName("home_music") val homeMusic: HomeMusic? = null,
    @SerialName("technician_pin") val technicianPin: PinHash? = null,
    val service: ServiceDto? = null,
    /** Hash of the hotel's adult-channel PIN; null = adult channels are not sent at all. */
    @SerialName("adult_pin") val adultPin: PinHash? = null,
    /** Current guest's favourite channel ids (cleared by the server at check-out). */
    val favorites: List<Long> = emptyList(),
    val announcements: List<AnnouncementDto> = emptyList(),
    val weather: Weather? = null,
    val config: ClientConfig = ClientConfig(),
)

@Serializable
data class Hotel(
    val name: String,
    val tagline: String? = null,
    val about: String? = null,
    @SerialName("logo_url") val logoUrl: String? = null,
    val address: String? = null,
    @SerialName("reception_phone") val receptionPhone: String? = null,
    @SerialName("reception_extension") val receptionExtension: String? = null,
    val wifi: Wifi? = null,
    @SerialName("check_in_time") val checkInTime: String = "14:00",
    @SerialName("check_out_time") val checkOutTime: String = "12:00",
    @SerialName("check_out_info") val checkOutInfo: String? = null,
    val timezone: String = "Asia/Jakarta",
)

@Serializable
data class Wifi(val ssid: String, val password: String? = null, @SerialName("qr_payload") val qrPayload: String)

@Serializable
data class RoomInfo(val number: String, val floor: Int)

@Serializable
data class GuestInfo(
    @SerialName("display_name") val displayName: String,
    val name: String,
    val salutation: String? = null,
    val language: String = "id",
)

@Serializable
data class SlideDto(
    val id: Long,
    val kicker: String? = null,
    val title: String,
    val description: String? = null,
    @SerialName("image_url") val imageUrl: String? = null,
)

@Serializable
data class AppDto(
    @SerialName("package_name") val packageName: String,
    val label: String,
    @SerialName("icon_url") val iconUrl: String? = null,
)

@Serializable
data class HomeMusic(val url: String, val volume: Int = 30)

@Serializable
data class PinHash(val algo: String, val iterations: Int, val salt: String, val hash: String)

@Serializable
data class Weather(val temperature: Double, val code: Int, val description: String)

@Serializable
data class ClientConfig(
    @SerialName("heartbeat_seconds") val heartbeatSeconds: Int = 60,
    @SerialName("slide_interval_seconds") val slideIntervalSeconds: Int = 6,
    /** Idle minutes before the TV's own screensaver; 0 = leave the TV setting alone. */
    @SerialName("screensaver_minutes") val screensaverMinutes: Int = 0,
    /** Volume % applied when the TV turns on; null = leave the TV's volume. */
    @SerialName("start_volume") val startVolume: Int? = null,
    /** Channel shown first when the TV menu opens after power-on; null = last watched. */
    @SerialName("start_channel_number") val startChannelNumber: Int? = null,
    @SerialName("show_hdmi_inputs") val showHdmiInputs: Boolean = true,
)

@Serializable
data class ChannelReportRequest(val status: String, val message: String? = null)

@Serializable
data class ChannelsResponse(
    @SerialName("expires_at") val expiresAt: String? = null,
    val categories: List<CategoryDto> = emptyList(),
    val channels: List<ChannelDto> = emptyList(),
)

@Serializable
data class CategoryDto(val id: Long, val name: String, val slug: String)

@Serializable
data class ChannelDto(
    val id: Long,
    val number: Int,
    val name: String,
    val category: String? = null,
    @SerialName("category_slug") val categorySlug: String? = null,
    @SerialName("source_type") val sourceType: String,
    val url: String,
    @SerialName("backup_url") val backupUrl: String? = null,
    val drm: Drm? = null,
    @SerialName("logo_url") val logoUrl: String? = null,
    @SerialName("sort_order") val sortOrder: Int = 0,
    @SerialName("is_adult") val isAdult: Boolean = false,
    @SerialName("has_epg") val hasEpg: Boolean = false,
) {
    val isProvider: Boolean get() = sourceType.startsWith("provider_")
}

@Serializable
data class Drm(
    val scheme: String,
    @SerialName("license_url") val licenseUrl: String,
    val headers: Map<String, String> = emptyMap(),
)

@Serializable
data class FacilitiesResponse(
    val facilities: List<FacilityDto> = emptyList(),
    val gallery: List<GalleryDto> = emptyList(),
)

@Serializable
data class GalleryDto(
    val id: Long,
    val album: String? = null,
    val title: String? = null,
    val caption: String? = null,
    @SerialName("image_url") val imageUrl: String,
    @SerialName("thumb_url") val thumbUrl: String,
)

@Serializable
data class FacilityDto(
    val id: Long,
    val category: String,
    val name: String,
    val description: String? = null,
    val location: String? = null,
    val hours: String? = null,
    @SerialName("image_url") val imageUrl: String? = null,
)

@Serializable
data class HeartbeatRequest(
    @SerialName("app_version") val appVersion: String,
    @SerialName("app_version_code") val appVersionCode: Long,
    @SerialName("kiosk_enabled") val kioskEnabled: Boolean,
    @SerialName("device_owner") val deviceOwner: Boolean,
    @SerialName("is_default_home") val isDefaultHome: Boolean,
    @SerialName("can_draw_overlays") val canDrawOverlays: Boolean,
    @SerialName("can_install_updates") val canInstallUpdates: Boolean,
    @SerialName("stock_launcher_active") val stockLauncherActive: Boolean,
    /** false while the TV is in standby (screen off) — shown red in the CMS. */
    @SerialName("screen_on") val screenOn: Boolean,
    /** TV is in Mode lepas (shown as "Dilepas" in CMS → TV Terdaftar). No default: always sent, so "false" clears it. */
    @SerialName("service_released") val serviceReleased: Boolean,
)

@Serializable
data class HeartbeatResponse(
    @SerialName("server_time") val serverTime: String,
    @SerialName("content_version") val contentVersion: String,
    val commands: List<CommandDto> = emptyList(),
    /** null = older server (keep what we have). */
    val announcements: List<AnnouncementDto>? = null,
    val service: ServiceDto? = null,
)

/** Hotel subscription state; "released" puts the TV in Mode lepas (back to its own launcher). */
@Serializable
data class ServiceDto(val status: String = "active", @SerialName("expires_at") val expiresAt: String? = null)

@Serializable
data class AnnouncementDto(
    val id: Long,
    /** Changes when the announcement is edited, so a dismissed one shows again. */
    val version: String,
    val title: String,
    val message: String,
    /** "ticker" | "fullscreen" */
    val style: String = "ticker",
    /** "info" | "warning" | "emergency" */
    val level: String = "info",
    @SerialName("force_channel_number") val forceChannelNumber: Int? = null,
    @SerialName("ends_at") val endsAt: String? = null,
) {
    val isEmergency: Boolean get() = level == "emergency"
}

@Serializable
data class EpgResponse(
    @SerialName("generated_at") val generatedAt: String? = null,
    /** channel id (as string) → programmes ordered by start. */
    val programmes: Map<String, List<ProgrammeDto>> = emptyMap(),
)

@Serializable
data class ProgrammeDto(
    val start: String,
    val end: String,
    val title: String,
    val description: String? = null,
    val category: String? = null,
) {
    @kotlinx.serialization.Transient val startMs: Long = parseMs(start)
    @kotlinx.serialization.Transient val endMs: Long = parseMs(end)

    fun isOnAt(nowMs: Long) = nowMs in startMs until endMs

    /** 0..1 progress of a running programme. */
    fun progress(nowMs: Long): Float = if (endMs <= startMs) 0f else ((nowMs - startMs).toFloat() / (endMs - startMs)).coerceIn(0f, 1f)

    private companion object {
        fun parseMs(v: String): Long = runCatching { java.time.OffsetDateTime.parse(v).toInstant().toEpochMilli() }.getOrDefault(0L)
    }
}

@Serializable
data class FavoritesRequest(@SerialName("channel_ids") val channelIds: List<Long>)

@Serializable
data class ViewsRequest(val views: List<ViewDto>)

@Serializable
data class ViewDto(
    @SerialName("channel_id") val channelId: Long,
    @SerialName("started_at") val startedAt: String,
    val seconds: Long,
)

@Serializable
data class CommandDto(val id: Long, val type: String, val payload: JsonObject? = null)

@Serializable
data class AckRequest(val status: String, val result: JsonObject? = null)

@Serializable
data class PinVerifyRequest(val pin: String)

@Serializable
data class PinVerifyResponse(
    val ok: Boolean,
    val locked: Boolean = false,
    val remaining: Int? = null,
    @SerialName("retry_after") val retryAfter: Int? = null,
)

@Serializable
data class OfflineAttempts(val attempts: List<OfflineAttempt>)

@Serializable
data class OfflineAttempt(val at: String, val ok: Boolean)

@Serializable
data class TechnicianEvent(val event: String, val detail: String? = null)

@Serializable
data class UpdateResponse(val update: UpdateDto? = null)

@Serializable
data class UpdateDto(
    @SerialName("version_code") val versionCode: Long,
    @SerialName("version_name") val versionName: String,
    val sha256: String,
    val notes: String? = null,
    @SerialName("download_url") val downloadUrl: String,
)
