package com.sangatv.plus.data.local

import android.annotation.SuppressLint
import android.content.Context
import android.provider.Settings
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import java.util.UUID
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

/**
 * Device token encrypted with a non-exportable AES-GCM key in the Android Keystore.
 * Backups are disabled in the manifest, so the token never leaves this TV.
 */
class SecureStore(context: Context) {
    private val prefs = context.getSharedPreferences("secure", Context.MODE_PRIVATE)
    private val appContext = context.applicationContext

    @Volatile private var cachedToken: String? = null

    private fun key(): SecretKey {
        val ks = KeyStore.getInstance(KEYSTORE).apply { load(null) }
        (ks.getEntry(ALIAS, null) as? KeyStore.SecretKeyEntry)?.let { return it.secretKey }
        val gen = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, KEYSTORE)
        gen.init(
            KeyGenParameterSpec.Builder(ALIAS, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setKeySize(256)
                .build(),
        )
        return gen.generateKey()
    }

    var token: String?
        get() = cachedToken ?: prefs.getString(TOKEN, null)?.let { decrypt(it) }?.also { cachedToken = it }
        set(value) {
            cachedToken = value
            prefs.edit().apply {
                if (value == null) remove(TOKEN) else putString(TOKEN, encrypt(value))
            }.apply()
        }

    /** Multi-hotel: hotel code returned at registration, sent as X-SangaTV-Hotel (not a secret). */
    var hotel: String?
        get() = prefs.getString(HOTEL, null)
        set(value) {
            prefs.edit().apply { if (value == null) remove(HOTEL) else putString(HOTEL, value) }.apply()
        }

    /** Stable per-install ID sent at provisioning (ANDROID_ID is per signing key on API 26+). */
    @get:SuppressLint("HardwareIds")
    val deviceUid: String
        get() = prefs.getString(UID, null) ?: run {
            val androidId = Settings.Secure.getString(appContext.contentResolver, Settings.Secure.ANDROID_ID)
            val uid = if (!androidId.isNullOrBlank() && androidId != "9774d56d682e549c") "aid-$androidId" else "uuid-${UUID.randomUUID()}"
            prefs.edit().putString(UID, uid).apply()
            uid
        }

    private fun encrypt(plain: String): String {
        val c = Cipher.getInstance(TRANSFORM)
        c.init(Cipher.ENCRYPT_MODE, key())
        val out = c.iv + c.doFinal(plain.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(out, Base64.NO_WRAP)
    }

    private fun decrypt(stored: String): String? = runCatching {
        val bytes = Base64.decode(stored, Base64.NO_WRAP)
        val c = Cipher.getInstance(TRANSFORM)
        c.init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, bytes, 0, 12))
        String(c.doFinal(bytes, 12, bytes.size - 12), Charsets.UTF_8)
    }.getOrNull()

    private companion object {
        const val KEYSTORE = "AndroidKeyStore"
        const val ALIAS = "sangatv_token_key"
        const val TRANSFORM = "AES/GCM/NoPadding"
        const val HOTEL = "hotel"
        const val TOKEN = "token"
        const val UID = "device_uid"
    }
}
