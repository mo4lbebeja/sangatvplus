package com.sangatv.plus.device

import android.content.Context
import android.util.Log
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * "Mode lepas": the TV hands the screen back to its own launcher when
 *  - the hotel's service ended / was released in the CMS (server says "released"),
 *  - this TV was removed from the CMS (token revoked), or
 *  - the server has not been reachable for [OFFLINE_RELEASE_DAYS] days (contract over, server gone).
 * While released, SangaTV Plus never pulls itself to the front; if it is still the HOME app it shows a
 * neutral launcher (all installed apps + inputs) and offers to uninstall itself.
 * A later "active" answer from the server undoes a server/offline release automatically.
 */
object ServiceState {
    const val OFFLINE_RELEASE_DAYS = 30
    private const val PREFS = "service_state"
    private const val RELEASED = "released"
    private const val REASON = "reason"
    private const val LAST_CONTACT = "last_contact"

    enum class Reason { Server, Revoked, Offline }

    private val _released = MutableStateFlow(false)
    val released: StateFlow<Boolean> = _released.asStateFlow()

    @Volatile var reason: Reason? = null
        private set

    /** Fast check for code paths without a Context (AutoStart, LauncherWatch). */
    val isReleased: Boolean get() = _released.value

    fun init(context: Context) {
        val p = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        _released.value = p.getBoolean(RELEASED, false)
        reason = p.getString(REASON, null)?.let { runCatching { Reason.valueOf(it) }.getOrNull() }
        // First run: start the offline clock now, never release a TV that simply has not synced yet.
        if (!p.contains(LAST_CONTACT)) p.edit().putLong(LAST_CONTACT, System.currentTimeMillis()).apply()
    }

    /** Any successful answer from the SangaTV Plus server. */
    fun markContact(context: Context) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putLong(LAST_CONTACT, System.currentTimeMillis()).apply()
        if (_released.value && reason == Reason.Offline) restore(context)
    }

    /** Service status from bootstrap / heartbeat: "active" | "expiring" | "released". */
    fun onServerStatus(context: Context, status: String?) {
        when (status) {
            "released" -> release(context, Reason.Server)
            "active", "expiring" -> if (_released.value && reason != Reason.Revoked) restore(context)
        }
    }

    fun checkOffline(context: Context) {
        val last = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getLong(LAST_CONTACT, System.currentTimeMillis())
        if (!_released.value && System.currentTimeMillis() - last > OFFLINE_RELEASE_DAYS * 24 * 3600_000L) release(context, Reason.Offline)
    }

    fun release(context: Context, why: Reason) {
        if (_released.value && reason == why) return
        Log.i("ServiceState", "release: $why")
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean(RELEASED, true).putString(REASON, why.name).apply()
        reason = why
        _released.value = true
        // Undo what SangaTV Plus changed on the TV.
        SleepTimer.cancel()
        SleepTimer.restore(context)
        AdultLock.relock()
        runCatching { KioskManager(context).releaseAll() }
    }

    fun restore(context: Context) {
        Log.i("ServiceState", "restore (was $reason)")
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean(RELEASED, false).remove(REASON).apply()
        reason = null
        _released.value = false
    }
}
