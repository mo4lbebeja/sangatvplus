package com.sangatv.plus

import android.app.Application
import coil3.ImageLoader
import coil3.PlatformContext
import coil3.SingletonImageLoader
import coil3.disk.DiskCache
import coil3.disk.directory
import coil3.memory.MemoryCache
import coil3.network.okhttp.OkHttpNetworkFetcherFactory
import com.sangatv.plus.data.Repository
import com.sangatv.plus.data.local.CacheStore
import com.sangatv.plus.data.local.PinGuard
import com.sangatv.plus.data.local.SecureStore
import com.sangatv.plus.data.remote.ApiClient
import com.sangatv.plus.device.AutoStart
import com.sangatv.plus.device.KioskManager
import com.sangatv.plus.device.OtaUpdater
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob

class SangaApp : Application(), SingletonImageLoader.Factory {
    val appScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    lateinit var secure: SecureStore
        private set
    lateinit var client: ApiClient
        private set
    lateinit var kiosk: KioskManager
        private set
    lateinit var repository: Repository
        private set

    override fun onCreate() {
        super.onCreate()
        com.sangatv.plus.device.ServiceState.init(this)
        secure = SecureStore(this)
        client = ApiClient { secure.token }
        kiosk = KioskManager(this)
        repository = Repository(
            appContext = this,
            client = client,
            secure = secure,
            cache = CacheStore(this),
            pinGuard = PinGuard(this, client.json),
            kiosk = kiosk,
            ota = OtaUpdater(this, client.api),
            scope = appScope,
        )
        repository.start()
        // Early boot: start the guard service right away (don't wait for the late BOOT_COMPLETED).
        if (AutoStart.isEarlyBoot()) AutoStart.startService(this)
    }

    /** Images (slides, logos) cached on disk so the launcher looks right offline. Small memory budget for 1.5–2 GB TVs. */
    override fun newImageLoader(context: PlatformContext): ImageLoader =
        ImageLoader.Builder(context)
            .components { add(OkHttpNetworkFetcherFactory(callFactory = { client.http })) }
            .memoryCache { MemoryCache.Builder().maxSizePercent(context, 0.15).build() }
            .diskCache { DiskCache.Builder().directory(cacheDir.resolve("images")).maxSizeBytes(150L * 1024 * 1024).build() }
            .build()
}
