package com.sangatv.plus.device

import android.util.Base64
import com.sangatv.plus.data.model.PinHash
import java.security.MessageDigest
import javax.crypto.SecretKeyFactory
import javax.crypto.spec.PBEKeySpec

/**
 * Adult-channel lock. The guest gets the PIN from reception; once entered, adult channels
 * stay open until the TV goes to standby (or 4 h). 5 wrong tries → locked for 10 minutes.
 */
object AdultLock {
    private const val UNLOCK_MS = 4 * 3600_000L
    private const val LOCKOUT_MS = 10 * 60_000L

    @Volatile private var unlockedUntil = 0L
    @Volatile private var fails = 0
    @Volatile var lockedOutUntil = 0L
        private set

    val isUnlocked: Boolean get() = System.currentTimeMillis() < unlockedUntil

    fun relock() {
        unlockedUntil = 0L
    }

    sealed interface Result {
        data object Ok : Result
        data class Wrong(val remaining: Int) : Result
        data object LockedOut : Result
    }

    fun verify(pin: String, hash: PinHash?): Result {
        val now = System.currentTimeMillis()
        if (now < lockedOutUntil) return Result.LockedOut
        val ok = hash != null && hash.algo == "pbkdf2-sha256" && runCatching {
            val calc = SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256")
                .generateSecret(PBEKeySpec(pin.toCharArray(), Base64.decode(hash.salt, Base64.DEFAULT), hash.iterations, 256)).encoded
            MessageDigest.isEqual(calc, Base64.decode(hash.hash, Base64.DEFAULT))
        }.getOrDefault(false)
        if (ok) {
            fails = 0
            unlockedUntil = now + UNLOCK_MS
            return Result.Ok
        }
        fails++
        if (fails >= 5) {
            fails = 0
            lockedOutUntil = now + LOCKOUT_MS
            return Result.LockedOut
        }
        return Result.Wrong(5 - fails)
    }
}
