package com.sangatv.plus.player

import android.content.Context
import android.net.wifi.WifiManager
import android.os.Handler
import android.os.Looper
import androidx.annotation.OptIn
import androidx.media3.common.C
import androidx.media3.common.MediaItem
import androidx.media3.common.MimeTypes
import androidx.media3.common.PlaybackException
import androidx.media3.common.Player
import androidx.media3.common.util.UnstableApi
import androidx.media3.datasource.DefaultDataSource
import androidx.media3.datasource.okhttp.OkHttpDataSource
import androidx.media3.exoplayer.DefaultLoadControl
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.exoplayer.source.DefaultMediaSourceFactory
import androidx.media3.extractor.DefaultExtractorsFactory
import androidx.media3.extractor.ts.DefaultTsPayloadReaderFactory
import com.sangatv.plus.data.model.ChannelDto
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import okhttp3.OkHttpClient
import java.util.concurrent.TimeUnit

/**
 * One ExoPlayer for live TV. Handles HLS, DASH (+Widevine) and UDP multicast MPEG-TS,
 * and fails over to the channel's backup URL automatically. Created when the TV screen
 * opens and released when it closes, to keep memory low on 1.5–2 GB TVs.
 */
@OptIn(UnstableApi::class)
class PlayerController(context: Context, private val onReport: (ChannelDto, String, String?) -> Unit = { _, _, _ -> }) {

    enum class Status { Idle, Buffering, Playing, FailingOver, Failed }

    private val appContext = context.applicationContext
    private val main = Handler(Looper.getMainLooper())

    // Streams use a plain client: no API token, no API pinning (sources are other hosts).
    private val streamHttp = OkHttpClient.Builder()
        .connectTimeout(6, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .build()

    private val extractors = DefaultExtractorsFactory()
        .setTsExtractorFlags(
            DefaultTsPayloadReaderFactory.FLAG_ALLOW_NON_IDR_KEYFRAMES or DefaultTsPayloadReaderFactory.FLAG_DETECT_ACCESS_UNITS,
        )

    val player: ExoPlayer = ExoPlayer.Builder(appContext)
        .setMediaSourceFactory(
            DefaultMediaSourceFactory(
                DefaultDataSource.Factory(appContext, OkHttpDataSource.Factory(streamHttp).setUserAgent("SangaTVPlus")),
                extractors,
            ),
        )
        .setLoadControl(
            DefaultLoadControl.Builder()
                .setBufferDurationsMs(4_000, 15_000, 1_000, 2_000)
                .setPrioritizeTimeOverSizeThresholds(true)
                .build(),
        )
        .build()
        .apply { playWhenReady = true }

    private val multicastLock = (appContext.getSystemService(Context.WIFI_SERVICE) as? WifiManager)
        ?.createMulticastLock("sangatv-iptv")?.apply { setReferenceCounted(false) }

    private val _status = MutableStateFlow(Status.Idle)
    val status: StateFlow<Status> = _status.asStateFlow()

    private var current: ChannelDto? = null
    private var usingBackup = false
    private var hadProblem = false

    private val retryPrimary = Runnable { current?.let { play(it, force = true) } }

    init {
        player.addListener(object : Player.Listener {
            override fun onPlaybackStateChanged(state: Int) {
                _status.value = when (state) {
                    Player.STATE_BUFFERING -> if (_status.value == Status.FailingOver) Status.FailingOver else Status.Buffering
                    Player.STATE_READY -> {
                        if (hadProblem) current?.let { onReport(it, "recovered", if (usingBackup) "via cadangan" else null) }
                        hadProblem = false
                        Status.Playing
                    }
                    else -> _status.value
                }
            }

            override fun onPlayerError(error: PlaybackException) {
                val ch = current ?: return
                // Live stream fell behind: just rejoin the live edge.
                if (error.errorCode == PlaybackException.ERROR_CODE_BEHIND_LIVE_WINDOW) {
                    player.seekToDefaultPosition()
                    player.prepare()
                    return
                }
                hadProblem = true
                if (!usingBackup && !ch.backupUrl.isNullOrBlank()) {
                    onReport(ch, "failover", error.errorCodeName)
                    usingBackup = true
                    _status.value = Status.FailingOver
                    load(ch, ch.backupUrl)
                } else {
                    _status.value = Status.Failed
                    onReport(ch, "failed", error.errorCodeName)
                    usingBackup = false
                    main.removeCallbacks(retryPrimary)
                    main.postDelayed(retryPrimary, 30_000)
                }
            }
        })
    }

    fun play(channel: ChannelDto, force: Boolean = false) {
        if (!force && current?.id == channel.id && current?.url == channel.url && _status.value != Status.Failed) return
        main.removeCallbacks(retryPrimary)
        current = channel
        usingBackup = false
        load(channel, channel.url)
    }

    private fun load(channel: ChannelDto, url: String) {
        if (url.startsWith("udp://") || url.startsWith("rtp://")) multicastLock?.acquire() else multicastLock?.release()

        val builder = MediaItem.Builder().setUri(url).setMediaId(channel.id.toString())
        when (channel.sourceType) {
            "local_hls", "provider_hls" -> builder.setMimeType(MimeTypes.APPLICATION_M3U8)
            "provider_dash" -> builder.setMimeType(MimeTypes.APPLICATION_MPD)
            "local_multicast" -> if (url.startsWith("udp://") || url.startsWith("rtp://")) builder.setMimeType(MimeTypes.VIDEO_MP2T)
        }
        channel.drm?.takeIf { it.scheme.equals("widevine", true) }?.let { drm ->
            builder.setDrmConfiguration(
                MediaItem.DrmConfiguration.Builder(C.WIDEVINE_UUID)
                    .setLicenseUri(drm.licenseUrl)
                    .setLicenseRequestHeaders(drm.headers)
                    .setMultiSession(false)
                    .build(),
            )
        }
        builder.setLiveConfiguration(MediaItem.LiveConfiguration.Builder().setTargetOffsetMs(6_000).build())

        player.setMediaItem(builder.build())
        player.prepare()
        player.play()
    }

    fun release() {
        main.removeCallbacks(retryPrimary)
        multicastLock?.release()
        player.release()
    }
}
