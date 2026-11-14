package com.sangatv.plus.device

import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageInstaller
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import com.sangatv.plus.BuildConfig
import com.sangatv.plus.data.model.UpdateDto
import com.sangatv.plus.data.remote.ApiService
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.security.MessageDigest

/**
 * OTA from our own server. An update is installed only if:
 *  1. its SHA-256 matches what the server announced,
 *  2. it is the same package with a higher versionCode, and
 *  3. it is signed by exactly the same certificate(s) as the running app.
 * (Android also enforces 3 on install; we check first so a bad file is never committed.)
 */
class OtaUpdater(private val context: Context, private val api: ApiService) {

    suspend fun checkAndInstall(): String = withContext(Dispatchers.IO) {
        val update = api.checkUpdate(BuildConfig.VERSION_CODE.toLong()).update ?: return@withContext "Sudah versi terbaru"
        val apk = File(context.cacheDir, "update.apk")
        try {
            download(update, apk)
            verify(update, apk)
            install(apk)
            "Memasang versi ${update.versionName}…"
        } catch (e: Exception) {
            Log.w(TAG, "OTA rejected", e)
            apk.delete()
            "Update ditolak: ${e.message}"
        }
    }

    private suspend fun download(update: UpdateDto, out: File) {
        api.download(update.downloadUrl).use { body ->
            out.outputStream().use { body.byteStream().copyTo(it) }
        }
    }

    private fun verify(update: UpdateDto, apk: File) {
        val digest = MessageDigest.getInstance("SHA-256")
        apk.inputStream().use { input ->
            val buf = ByteArray(64 * 1024)
            while (true) {
                val n = input.read(buf)
                if (n < 0) break
                digest.update(buf, 0, n)
            }
        }
        val sha = digest.digest().joinToString("") { "%02x".format(it) }
        check(sha.equals(update.sha256, ignoreCase = true)) { "checksum tidak cocok" }

        val pm = context.packageManager
        val flags = PackageManager.GET_SIGNING_CERTIFICATES
        val archive = pm.getPackageArchiveInfo(apk.absolutePath, flags) ?: error("APK tidak valid")
        check(archive.packageName == context.packageName) { "package berbeda" }
        check(archive.longVersionCode > BuildConfig.VERSION_CODE) { "versi tidak lebih baru" }

        val mine = pm.getPackageInfo(context.packageName, flags)
        check(signers(archive).isNotEmpty() && signers(archive) == signers(mine)) { "tanda tangan APK berbeda" }
    }

    private fun signers(info: PackageInfo): Set<String> {
        val si = info.signingInfo ?: return emptySet()
        val sigs = if (si.hasMultipleSigners()) si.apkContentsSigners else si.signingCertificateHistory
        return sigs.orEmpty().map { sig ->
            MessageDigest.getInstance("SHA-256").digest(sig.toByteArray()).joinToString("") { "%02x".format(it) }
        }.toSet()
    }

    private fun install(apk: File) {
        val installer = context.packageManager.packageInstaller
        val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL).apply {
            setAppPackageName(context.packageName)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
            }
        }
        val id = installer.createSession(params)
        installer.openSession(id).use { session ->
            session.openWrite("base.apk", 0, apk.length()).use { out ->
                apk.inputStream().use { it.copyTo(out) }
                session.fsync(out)
            }
            val pi = PendingIntent.getBroadcast(
                context, id, Intent(context, InstallResultReceiver::class.java),
                PendingIntent.FLAG_MUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
            )
            session.commit(pi.intentSender)
        }
    }

    private companion object {
        const val TAG = "OtaUpdater"
    }
}
