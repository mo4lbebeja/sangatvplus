# kotlinx.serialization: keep generated serializers for API DTOs.
-keepattributes *Annotation*, InnerClasses, Signature, Exceptions
-keepclassmembers @kotlinx.serialization.Serializable class com.sangatv.plus.** {
    *** Companion;
    kotlinx.serialization.KSerializer serializer(...);
}
-keep,includedescriptorclasses class com.sangatv.plus.**$$serializer { *; }

# Retrofit service interfaces are used via reflection.
-keep,allowobfuscation interface com.sangatv.plus.data.remote.ApiService
-keepattributes RuntimeVisibleAnnotations, RuntimeVisibleParameterAnnotations
-dontwarn okhttp3.internal.platform.**
-dontwarn org.conscrypt.**
-dontwarn org.bouncycastle.**
-dontwarn org.openjsse.**

# Device admin receiver is referenced from the manifest.
-keep class com.sangatv.plus.device.SangaDeviceAdminReceiver
