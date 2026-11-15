import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
}

val debugApiBaseUrl = providers.gradleProperty("sangatv.debugApiBaseUrl").getOrElse("http://10.0.2.2:8000/")
val releaseApiBaseUrl = providers.gradleProperty("sangatv.releaseApiBaseUrl").getOrElse("")
val certPins = providers.gradleProperty("sangatv.certPins").getOrElse("")

fun String.asBuildConfigString() = "\"" + replace("\\", "\\\\").replace("\"", "\\\"") + "\""

android {
    lint {
        // getString() in click handlers is fine for this app (activity is recreated on locale change).
        warning += "LocalContextGetResourceValueCall"
    }
    namespace = "com.sangatv.plus"
    compileSdk {
        version = release(37)
    }

    val keystoreProps = Properties().apply {
        rootProject.file("keystore.properties").takeIf { it.exists() }?.inputStream()?.use { load(it) }
    }
    signingConfigs {
        if (keystoreProps.getProperty("storeFile") != null) {
            create("release") {
                storeFile = rootProject.file(keystoreProps.getProperty("storeFile"))
                storePassword = keystoreProps.getProperty("storePassword")
                keyAlias = keystoreProps.getProperty("keyAlias")
                keyPassword = keystoreProps.getProperty("keyPassword")
            }
        }
    }

    defaultConfig {
        applicationId = "com.sangatv.plus"
        minSdk = 28          // Android 9 (oldest TCL Android TV in scope)
        targetSdk = 36
        versionCode = 7
        versionName = "0.2.4"
        buildConfigField("String", "CERT_PINS", certPins.asBuildConfigString())
    }

    buildTypes {
        debug {
            applicationIdSuffix = ".debug"
            buildConfigField("String", "API_BASE_URL", debugApiBaseUrl.asBuildConfigString())
        }
        // Field testing on real TVs: optimized like release (R8, not debuggable → fast start on
        // 1 GB TVs), but signed with the debug key and allowed to use the laptop's http server.
        create("staging") {
            initWith(getByName("release"))
            applicationIdSuffix = ".staging"
            isDebuggable = false
            signingConfig = signingConfigs.getByName("debug")
            matchingFallbacks += listOf("release")
            buildConfigField("String", "API_BASE_URL", debugApiBaseUrl.asBuildConfigString())
        }
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            buildConfigField("String", "API_BASE_URL", releaseApiBaseUrl.asBuildConfigString())
            // Never ship a release signed with the debug key.
            signingConfig = signingConfigs.findByName("release")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
}

// Security gate: a release build must talk HTTPS to a pinned server.
tasks.matching { it.name == "preReleaseBuild" }.configureEach {
    val api = releaseApiBaseUrl
    val pins = certPins
    doFirst {
        if (!api.startsWith("https://")) {
            throw GradleException("sangatv.releaseApiBaseUrl must be an https:// URL for release builds.")
        }
        if (pins.split(',').count { it.trim().startsWith("sha256/") } < 2) {
            throw GradleException("sangatv.certPins needs at least 2 sha256/ pins (current + backup) for release builds.")
        }
    }
}

dependencies {
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.foundation)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.tv.material)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.process)

    implementation(libs.media3.exoplayer)
    implementation(libs.media3.exoplayer.hls)
    implementation(libs.media3.exoplayer.dash)
    implementation(libs.media3.datasource.okhttp)
    implementation(libs.media3.ui.compose)

    implementation(libs.retrofit)
    implementation(libs.retrofit.kotlinx.serialization)
    implementation(libs.okhttp)
    implementation(libs.kotlinx.serialization.json)
    implementation(libs.kotlinx.coroutines.android)
    implementation(libs.coil.compose)
    implementation(libs.coil.network.okhttp)
    implementation(libs.zxing.core)

    testImplementation(libs.junit)
    debugImplementation(libs.androidx.compose.ui.tooling)
}
