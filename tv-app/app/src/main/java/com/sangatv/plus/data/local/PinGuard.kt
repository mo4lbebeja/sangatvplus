package com.sangatv.plus.data.local

import android.content.Context
import android.util.Base64
import com.sangatv.plus.data.model.OfflineAttempt
import com.sangatv.plus.data.model.PinHash
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.json.Json
import java.security.MessageDigest
import java.time.Instant
import javax.crypto.SecretKeyFactory
import javax.crypto.spec.PBEKeySpec

/**
 * Offline fallback for the technician PIN: verifies against the PBKDF2 hash
 * delivered by the server, with the same 5-wrong → 15-minute lockout, and
 * queues every attempt so it can be reported to the server's audit log later.
 */
class PinGuard(context: Context, private val json: Json) {
    private val prefs = context.getSharedPreferences("pin_guard", Context.MODE_PRIVATE)

    val lockedUntilMs: Long get() = prefs.getLong(LOCKED_UNTIL, 0)

    fun isLocked(now: Long = System.currentTimeMillis()) = lockedUntilMs > now

    fun verifyOffline(pin: String, hash: PinHash?): Result {
        if (isLocked()) return Result.Locked(lockedUntilMs)
        if (hash == null || hash.algo != "pbkdf2-sha256") return Result.NoPin

        val salt = Base64.decode(hash.salt, Base64.DEFAULT)
        val spec = PBEKeySpec(pin.toCharArray(), salt, hash.iterations, 256)
        val calc = SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256").generateSecret(spec).encoded
        val ok = MessageDigest.isEqual(calc, Base64.decode(hash.hash, Base64.DEFAULT))
        queue(ok)

        return if (ok) {
            resetFailures()
            Result.Ok
        } else {
            registerFailure()
        }
    }

    /** Mirror server-side outcomes locally so the lockout also holds if the network drops. */
    fun registerFailure(): Result {
        val fails = prefs.getInt(FAILS, 0) + 1
        return if (fails >= MAX_ATTEMPTS) {
            val until = System.currentTimeMillis() + LOCK_MS
            prefs.edit().putInt(FAILS, 0).putLong(LOCKED_UNTIL, until).apply()
            Result.Locked(until)
        } else {
            prefs.edit().putInt(FAILS, fails).apply()
            Result.Wrong(MAX_ATTEMPTS - fails)
        }
    }

    fun lockUntil(untilMs: Long) {
        prefs.edit().putInt(FAILS, 0).putLong(LOCKED_UNTIL, untilMs).apply()
    }

    fun resetFailures() {
        prefs.edit().putInt(FAILS, 0).putLong(LOCKED_UNTIL, 0).apply()
    }

    private val listSer = ListSerializer(OfflineAttempt.serializer())

    private fun queue(ok: Boolean) {
        val list = pending() + OfflineAttempt(Instant.now().toString(), ok)
        prefs.edit().putString(PENDING, json.encodeToString(listSer, list.takeLast(100))).apply()
    }

    fun pending(): List<OfflineAttempt> =
        prefs.getString(PENDING, null)?.let { runCatching { json.decodeFromString(listSer, it) }.getOrNull() } ?: emptyList()

    fun clearPending() {
        prefs.edit().remove(PENDING).apply()
    }

    sealed interface Result {
        data object Ok : Result
        data object NoPin : Result
        data class Wrong(val remaining: Int) : Result
        data class Locked(val untilMs: Long) : Result
    }

    companion object {
        const val MAX_ATTEMPTS = 5
        const val LOCK_MS = 15 * 60 * 1000L
        private const val FAILS = "fails"
        private const val LOCKED_UNTIL = "locked_until"
        private const val PENDING = "pending"
    }
}
