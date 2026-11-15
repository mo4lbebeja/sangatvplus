package com.sangatv.plus.device

import android.content.Context
import android.content.Intent
import android.media.AudioManager
import android.media.tv.TvContract
import android.media.tv.TvInputInfo
import android.media.tv.TvInputManager
import android.util.Log

/** Hotel defaults applied on power-on (volume, first channel) and HDMI input access. */
object TvDefaults {
    /** Set on power-on / wake; the first TV-menu open after that uses the hotel's start channel. */
    @Volatile var freshSession = true

    fun applyVolume(context: Context, percent: Int?) {
        if (percent == null || percent !in 0..100) return
        runCatching {
            val am = context.getSystemService(AudioManager::class.java)
            val max = am.getStreamMaxVolume(AudioManager.STREAM_MUSIC)
            am.setStreamVolume(AudioManager.STREAM_MUSIC, (max * percent + 50) / 100, 0)
        }.onFailure { Log.w("TvDefaults", "cannot set volume", it) }
    }

    data class HdmiInput(val id: String, val label: String)

    fun hdmiInputs(context: Context): List<HdmiInput> = runCatching {
        val tim = context.getSystemService(TvInputManager::class.java) ?: return emptyList()
        tim.tvInputList
            .filter { it.type == TvInputInfo.TYPE_HDMI && !it.isHidden(context) && it.parentId == null }
            .sortedBy { it.id }
            .mapIndexed { i, info ->
                val label = info.loadCustomLabel(context)?.toString()?.takeIf { it.isNotBlank() }
                    ?: info.loadLabel(context)?.toString()?.takeIf { it.isNotBlank() && !it.contains("Service", true) }
                    ?: "HDMI ${i + 1}"
                HdmiInput(info.id, label)
            }
    }.getOrDefault(emptyList())

    fun hdmiIntent(id: String): Intent =
        Intent(Intent.ACTION_VIEW, TvContract.buildChannelUriForPassthroughInput(id)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

    /** Packages that show HDMI (the TV's own Live TV app) — allowed in kiosk mode. */
    fun hdmiHandlerPackages(context: Context): List<String> = runCatching {
        val probe = Intent(Intent.ACTION_VIEW).setDataAndType(TvContract.Channels.CONTENT_URI, "vnd.android.cursor.item/channel")
        context.packageManager.queryIntentActivities(probe, 0).map { it.activityInfo.packageName }.distinct()
    }.getOrDefault(emptyList())
}
