package com.sangatv.plus.player

import android.content.Context
import androidx.annotation.OptIn
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.media3.common.AudioAttributes
import androidx.media3.common.C
import androidx.media3.common.MediaItem
import androidx.media3.common.Player
import androidx.media3.common.util.UnstableApi
import androidx.media3.exoplayer.ExoPlayer
import com.sangatv.plus.data.model.HomeMusic
import kotlinx.coroutines.delay

/**
 * Looping background music for the Home screen. Lives only while Home is shown
 * (released on leave → no memory cost while watching TV) and pauses when another
 * app (YouTube, Netflix …) comes to the front.
 */
@OptIn(UnstableApi::class)
@Composable
fun HomeMusicPlayer(music: HomeMusic?) {
    if (music == null) return
    val context = LocalContext.current
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    val target = (music.volume.coerceIn(0, 100)) / 100f

    val player = remember(music.url) { buildPlayer(context, music.url) }

    // Gentle fade-in so the music doesn't start abruptly.
    LaunchedEffect(player, target) {
        player.volume = 0f
        repeat(20) { i ->
            player.volume = target * (i + 1) / 20f
            delay(75)
        }
    }

    DisposableEffect(player, lifecycle) {
        val observer = LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_START -> player.play()
                Lifecycle.Event.ON_STOP -> player.pause()
                else -> Unit
            }
        }
        lifecycle.addObserver(observer)
        if (lifecycle.currentState.isAtLeast(Lifecycle.State.STARTED)) player.play()
        onDispose {
            lifecycle.removeObserver(observer)
            player.release()
        }
    }
}

@OptIn(UnstableApi::class)
private fun buildPlayer(context: Context, url: String): ExoPlayer =
    ExoPlayer.Builder(context.applicationContext)
        .setAudioAttributes(
            AudioAttributes.Builder().setUsage(C.USAGE_MEDIA).setContentType(C.AUDIO_CONTENT_TYPE_MUSIC).build(),
            /* handleAudioFocus = */ true,
        )
        .build()
        .apply {
            setMediaItem(MediaItem.fromUri(url))
            repeatMode = Player.REPEAT_MODE_ONE
            volume = 0f
            prepare()
        }
