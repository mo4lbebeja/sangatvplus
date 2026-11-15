package com.sangatv.plus.ui.screens

import android.content.Intent
import android.widget.Toast
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.core.graphics.drawable.toBitmap
import androidx.tv.material3.Text
import coil3.compose.AsyncImage
import com.sangatv.plus.R
import com.sangatv.plus.data.model.AppDto
import com.sangatv.plus.device.AutoStart
import com.sangatv.plus.device.TvDefaults
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.width
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.KeyHint
import com.sangatv.plus.ui.components.card
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px

/** Only apps whitelisted in the CMS are shown (and allowed in kiosk mode). */
@Composable
fun AppsScreen(apps: List<AppDto>, showHdmi: Boolean = true) {
    val context = LocalContext.current
    val pm = context.packageManager
    val first = remember { FocusRequester() }
    LaunchedEffect(apps.isNotEmpty()) { if (apps.isNotEmpty()) runCatching { first.requestFocus() } }

    fun launchIntent(pkg: String): Intent? = pm.getLeanbackLaunchIntentForPackage(pkg) ?: pm.getLaunchIntentForPackage(pkg)

    Column(
        Modifier.fillMaxSize().background(Sanga.Background).padding(start = 96.px, top = 72.px, end = 96.px, bottom = 56.px),
        verticalArrangement = Arrangement.spacedBy(40.px),
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text(stringResource(R.string.apps_title), style = body(56, 800), modifier = Modifier.weight(1f))
            KeyHint("BACK", stringResource(R.string.menu_home))
        }
        val hdmi = remember(showHdmi) { if (showHdmi) TvDefaults.hdmiInputs(context) else emptyList() }
        if (hdmi.isNotEmpty()) {
            Text(stringResource(R.string.inputs_title), style = body(28, 700, Sanga.Text2))
            Row(horizontalArrangement = Arrangement.spacedBy(24.px)) {
                hdmi.forEach { input ->
                    Focusable(onClick = {
                        AutoStart.markIntentionalExit()
                        runCatching { context.startActivity(TvDefaults.hdmiIntent(input.id)) }
                            .onFailure { Toast.makeText(context, R.string.input_unavailable, Toast.LENGTH_SHORT).show() }
                    }) { focused ->
                        Row(
                            Modifier.width(300.px).height(110.px)
                                .card(RoundedCornerShape(20.px), borderColor = if (focused) Sanga.Accent else Sanga.Border, borderWidth = if (focused) 4.px else 1.px)
                                .padding(horizontal = 28.px),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(18.px),
                        ) {
                            com.sangatv.plus.ui.components.SIcon(com.sangatv.plus.ui.components.SIcons.Hdmi, if (focused) Sanga.Accent else Sanga.Text2, 44.px)
                            Text(input.label, style = body(26, 700, if (focused) Sanga.Accent else Sanga.Text), maxLines = 1)
                        }
                    }
                }
            }
            Text(stringResource(R.string.apps_title), style = body(28, 700, Sanga.Text2))
        }
        if (apps.isEmpty()) {
            Text(stringResource(R.string.apps_empty), style = body(26, 500, Sanga.Text3))
            return@Column
        }
        LazyVerticalGrid(
            columns = GridCells.Fixed(4),
            horizontalArrangement = Arrangement.spacedBy(32.px),
            verticalArrangement = Arrangement.spacedBy(32.px),
        ) {
            itemsIndexed(apps, key = { _, a -> a.packageName }) { i, app ->
                val intent = remember(app.packageName) { launchIntent(app.packageName) }
                val banner = remember(app.packageName) {
                    runCatching { (pm.getApplicationBanner(app.packageName) ?: pm.getApplicationIcon(app.packageName)).toBitmap().asImageBitmap() }.getOrNull()
                }
                Focusable(
                    onClick = {
                        if (intent != null) {
                            AutoStart.markIntentionalExit()
                            context.startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                        }
                        else Toast.makeText(context, R.string.app_not_installed, Toast.LENGTH_SHORT).show()
                    },
                    modifier = if (i == 0) Modifier.focusRequester(first) else Modifier,
                ) { focused ->
                    Column(
                        Modifier.alpha(if (intent == null) 0.45f else 1f),
                        verticalArrangement = Arrangement.spacedBy(14.px),
                    ) {
                        Box(
                            Modifier.fillMaxWidth().aspectRatio(16f / 9f)
                                .card(RoundedCornerShape(20.px), borderColor = if (focused) Sanga.Accent else Sanga.Border, borderWidth = if (focused) 4.px else 1.px),
                            contentAlignment = Alignment.Center,
                        ) {
                            when {
                                app.iconUrl != null -> AsyncImage(app.iconUrl, app.label, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize().padding(24.px))
                                banner != null -> Image(banner, app.label, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize().padding(16.px))
                                else -> Text(app.label.take(1), style = body(64, 800, Sanga.Text3))
                            }
                        }
                        Text(app.label, style = body(24, 600, if (focused) Sanga.Accent else Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }
}

