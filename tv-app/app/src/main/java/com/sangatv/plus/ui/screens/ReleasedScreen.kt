package com.sangatv.plus.ui.screens

import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.provider.Settings
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.core.graphics.drawable.toBitmap
import androidx.tv.material3.Text
import com.sangatv.plus.R
import com.sangatv.plus.SangaApp
import com.sangatv.plus.device.AutoStart
import com.sangatv.plus.device.ServiceState
import com.sangatv.plus.device.TvDefaults
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.card
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.delay

/** Why the neutral launcher is shown. */
enum class NeutralMode { Released, Revoked, Offline, Unregistered }

private data class LaunchItem(val label: String, val intent: Intent, val packageName: String?, val icon: ImageVector? = null)

/**
 * Neutral launcher for Mode lepas (and for a TV that is not registered yet): no hotel branding,
 * every installed app, the TV inputs and settings, a way to the TV's own home screen and an uninstall
 * button. SangaTV Plus never forces itself to the front while this is showing.
 */
@Composable
fun ReleasedScreen(mode: NeutralMode, onRegister: (() -> Unit)?) {
    val context = LocalContext.current
    val pm = context.packageManager
    BackHandler { /* stays here: this is the HOME screen while released */ }

    val items = remember {
        val own = context.packageName
        val apps = (listOf(Intent.CATEGORY_LEANBACK_LAUNCHER, Intent.CATEGORY_LAUNCHER)).flatMap { cat ->
            pm.queryIntentActivities(Intent(Intent.ACTION_MAIN).addCategory(cat), 0)
        }
            .map { it.activityInfo }
            .filter { it.packageName != own }
            .distinctBy { it.packageName }
            .mapNotNull { ai ->
                val intent = pm.getLeanbackLaunchIntentForPackage(ai.packageName) ?: pm.getLaunchIntentForPackage(ai.packageName) ?: return@mapNotNull null
                LaunchItem(ai.loadLabel(pm).toString(), intent, ai.packageName)
            }
            .sortedBy { it.label.lowercase() }
        val inputs = TvDefaults.hdmiInputs(context).map { LaunchItem(it.label, TvDefaults.hdmiIntent(it.id), null, SIcons.Hdmi) }
        inputs + apps
    }
    val stockHome = remember {
        AutoStart.activeStockLaunchers(context).firstNotNullOfOrNull { pkg ->
            Intent(Intent.ACTION_MAIN).addCategory(Intent.CATEGORY_HOME).setPackage(pkg).takeIf { pm.resolveActivity(it, 0) != null }
        }
    }

    fun launch(intent: Intent) {
        AutoStart.markIntentionalExit()
        runCatching { context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) }
            .onFailure { Toast.makeText(context, R.string.app_not_installed, Toast.LENGTH_SHORT).show() }
    }

    val first = remember { FocusRequester() }
    LaunchedEffect(Unit) { delay(100); runCatching { first.requestFocus() } }

    Column(
        Modifier.fillMaxSize().background(Sanga.Background).padding(start = 96.px, top = 64.px, end = 96.px, bottom = 40.px),
        verticalArrangement = Arrangement.spacedBy(28.px),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(8.px)) {
            Text(
                stringResource(
                    when (mode) {
                        NeutralMode.Released -> R.string.released_title
                        NeutralMode.Revoked -> R.string.revoked_title
                        NeutralMode.Offline -> R.string.offline_release_title
                        NeutralMode.Unregistered -> R.string.unregistered_title
                    },
                ),
                style = body(40, 800),
            )
            Text(
                stringResource(
                    when (mode) {
                        NeutralMode.Released -> R.string.released_desc
                        NeutralMode.Revoked -> R.string.revoked_desc
                        NeutralMode.Offline -> R.string.offline_release_desc
                        NeutralMode.Unregistered -> R.string.unregistered_desc
                    },
                    ServiceState.OFFLINE_RELEASE_DAYS,
                ),
                style = body(21, 400, Sanga.Text3),
            )
        }

        // Actions
        Row(horizontalArrangement = Arrangement.spacedBy(18.px)) {
            if (stockHome != null) ActionChip(stringResource(R.string.open_tv_home), SIcons.Home, Modifier.focusRequester(first)) { launch(stockHome) }
            ActionChip(stringResource(R.string.tv_settings), SIcons.Info, if (stockHome == null) Modifier.focusRequester(first) else Modifier) {
                launch(Intent(Settings.ACTION_SETTINGS))
            }
            if (onRegister != null) ActionChip(stringResource(R.string.register_tv), SIcons.Tv) { onRegister() }
            if (mode != NeutralMode.Unregistered) {
                ActionChip(stringResource(R.string.uninstall_app), SIcons.Lock) {
                    (context.applicationContext as SangaApp).kiosk.prepareUninstall()
                    launch(Intent(Intent.ACTION_DELETE, Uri.parse("package:${context.packageName}")))
                }
            }
        }

        LazyVerticalGrid(
            columns = GridCells.Fixed(6),
            horizontalArrangement = Arrangement.spacedBy(24.px),
            verticalArrangement = Arrangement.spacedBy(24.px),
            contentPadding = PaddingValues(top = 8.px, bottom = 40.px),
        ) {
            itemsIndexed(items, key = { i, it -> (it.packageName ?: it.label) + i }) { _, item ->
                val banner = remember(item.packageName) {
                    item.packageName?.let { pkg -> runCatching { (pm.getApplicationBanner(pkg) ?: pm.getApplicationIcon(pkg)).toBitmap().asImageBitmap() }.getOrNull() }
                }
                Focusable(onClick = { launch(item.intent) }) { focused ->
                    Column(verticalArrangement = Arrangement.spacedBy(10.px)) {
                        Box(
                            Modifier.fillMaxWidth().aspectRatio(16f / 9f)
                                .card(RoundedCornerShape(14.px), borderColor = if (focused) Sanga.Accent else Sanga.Border, borderWidth = if (focused) 3.px else 1.px),
                            contentAlignment = Alignment.Center,
                        ) {
                            when {
                                banner != null -> Image(banner, item.label, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize().padding(8.px))
                                item.icon != null -> SIcon(item.icon, if (focused) Sanga.Accent else Sanga.Text2, 40.px)
                                else -> Text(item.label.take(1), style = body(40, 800, Sanga.Text3))
                            }
                        }
                        Text(item.label, style = body(20, 600, if (focused) Sanga.Accent else Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }
}

@Composable
private fun ActionChip(label: String, icon: ImageVector, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Focusable(onClick = onClick, modifier = modifier) { focused ->
        Row(
            Modifier.height(64.px)
                .background(if (focused) Sanga.Accent else Sanga.Card, RoundedCornerShape(14.px))
                .padding(horizontal = 24.px),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.px),
        ) {
            SIcon(icon, if (focused) Sanga.OnAccent else Sanga.Text2, 26.px)
            Text(label, style = body(21, 700, if (focused) Sanga.OnAccent else Sanga.Text), maxLines = 1)
        }
    }
}
