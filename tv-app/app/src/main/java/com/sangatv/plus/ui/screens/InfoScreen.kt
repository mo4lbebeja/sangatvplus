package com.sangatv.plus.ui.screens

import androidx.compose.foundation.Image
import com.sangatv.plus.data.model.GalleryDto
import androidx.compose.ui.input.key.type
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.key
import androidx.compose.ui.graphics.Brush
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.focusable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.tv.material3.Text
import coil3.compose.AsyncImage
import com.sangatv.plus.R
import com.sangatv.plus.data.model.Bootstrap
import com.sangatv.plus.data.model.FacilityDto
import com.sangatv.plus.ui.InfoSection
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.TimeFmt
import com.sangatv.plus.ui.components.card
import com.sangatv.plus.ui.components.rememberNow
import com.sangatv.plus.ui.components.rememberQr
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.display
import com.sangatv.plus.ui.theme.px

private val SECTIONS = listOf(
    InfoSection.About to R.string.info_about,
    InfoSection.Facilities to R.string.info_facilities,
    InfoSection.Dining to R.string.info_dining,
    InfoSection.Gallery to R.string.info_gallery,
    InfoSection.Wifi to R.string.info_wifi,
    InfoSection.Contact to R.string.info_contact,
    InfoSection.Checkout to R.string.info_checkout,
)

@Composable
fun InfoScreen(data: Bootstrap?, facilities: List<FacilityDto>, gallery: List<GalleryDto>, start: InfoSection, onBack: () -> Unit) {
    var section by remember { mutableStateOf(start) }
    var viewer by remember { mutableStateOf<Int?>(null) }
    val now by rememberNow(data?.hotel?.timezone ?: "Asia/Jakarta")
    val startFocus = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { startFocus.requestFocus() } }

    val backdrop = data?.appsBackgroundUrl
    Box(Modifier.fillMaxSize().background(Sanga.Background)) {
    com.sangatv.plus.ui.components.PhotoBackdrop(backdrop)
    Row(Modifier.fillMaxSize()) {
        // Left menu (slightly see-through over the photo background)
        Column(
            Modifier.width(480.px).fillMaxHeight().background(if (backdrop != null) Sanga.Panel.copy(alpha = 0.78f) else Sanga.Panel)
                .padding(start = 72.px, top = 64.px, end = 40.px, bottom = 48.px),
            verticalArrangement = Arrangement.spacedBy(12.px),
        ) {
            Focusable(onClick = onBack, scaleOnFocus = false) { focused ->
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.px), modifier = Modifier.padding(bottom = 28.px)) {
                    SIcon(SIcons.ChevronLeft, if (focused) Sanga.Accent else Sanga.Text3, 24.px)
                    Text(stringResource(R.string.menu_home), style = body(22, 600, if (focused) Sanga.Accent else Sanga.Text3))
                }
            }
            Text(stringResource(R.string.menu_info), style = body(40, 800), modifier = Modifier.padding(bottom = 24.px))
            SECTIONS.forEach { (s, label) ->
                val selected = s == section
                Focusable(
                    onClick = { section = s },
                    scaleOnFocus = false,
                    modifier = Modifier
                        .fillMaxWidth()
                        .then(if (s == start) Modifier.focusRequester(startFocus) else Modifier)
                        .onFocusChanged { if (it.isFocused) section = s },
                ) { focused ->
                    Text(
                        stringResource(label),
                        style = body(25, if (selected) 700 else 600, if (selected) Sanga.OnAccent else Sanga.Text2),
                        modifier = Modifier.fillMaxWidth()
                            .background(if (selected) Sanga.Accent else if (focused) Sanga.Card else Color.Transparent, RoundedCornerShape(16.px))
                            .padding(horizontal = 24.px, vertical = 20.px),
                    )
                }
            }
        }
        Box(Modifier.width(1.px).fillMaxHeight().background(Sanga.Surface2))

        // Content
        Column(
            Modifier.weight(1f).fillMaxHeight().padding(start = 72.px, top = 64.px, end = 80.px, bottom = 56.px),
            verticalArrangement = Arrangement.spacedBy(40.px),
        ) {
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Bottom) {
                Column(Modifier.weight(1f)) {
                    Text(stringResource(SECTIONS.first { it.first == section }.second), style = body(56, 800))
                    Text(subtitle(section, data), style = body(24, 400, Sanga.Text3), modifier = Modifier.padding(top = 10.px))
                }
                Text(now.format(TimeFmt), style = body(30, 700, Sanga.Text2))
            }
            Row(Modifier.weight(1f), horizontalArrangement = Arrangement.spacedBy(40.px)) {
                Box(Modifier.weight(1f).fillMaxHeight()) {
                    when (section) {
                        InfoSection.About -> TextPanel(data?.hotel?.about ?: "", listOfNotNull(data?.hotel?.address))
                        InfoSection.Facilities -> FacilityGrid(facilities.filter { it.category == "facility" })
                        InfoSection.Dining -> FacilityGrid(facilities.filter { it.category == "dining" })
                        InfoSection.Gallery -> GalleryGrid(gallery) { viewer = it }
                        InfoSection.Wifi -> WifiBig(data)
                        InfoSection.Contact -> TextPanel(
                            data?.hotel?.receptionExtension?.let { stringResource(R.string.reception_dial, it) } ?: "",
                            listOfNotNull(data?.hotel?.receptionPhone),
                        )
                        InfoSection.Checkout -> TextPanel(
                            stringResource(R.string.checkout_time, data?.hotel?.checkOutTime ?: "12:00"),
                            listOfNotNull(data?.hotel?.checkOutInfo),
                        )
                    }
                }
                if (section != InfoSection.Gallery) {
                    Column(Modifier.width(440.px), verticalArrangement = Arrangement.spacedBy(24.px)) {
                        if (section != InfoSection.Wifi) WifiCard(data)
                        ReceptionCard(data)
                    }
                }
            }
        }
    }
    viewer?.let { start -> GalleryViewer(gallery, start) { viewer = null } }
    }
}

@Composable
private fun subtitle(section: InfoSection, data: Bootstrap?): String {
    val hotel = data?.hotel?.name ?: ""
    return when (section) {
        InfoSection.About -> data?.hotel?.tagline ?: hotel
        InfoSection.Facilities -> stringResource(R.string.facilities_sub, hotel)
        InfoSection.Dining -> stringResource(R.string.dining_sub)
        InfoSection.Gallery -> stringResource(R.string.gallery_sub, hotel)
        InfoSection.Wifi -> stringResource(R.string.wifi_scan)
        InfoSection.Contact -> stringResource(R.string.reception_24h)
        InfoSection.Checkout -> "Check-in ${data?.hotel?.checkInTime ?: ""} · Check-out ${data?.hotel?.checkOutTime ?: ""}"
    }
}

@Composable
private fun TextPanel(main: String, extra: List<String>) {
    // Focusable so long text can be scrolled with the remote.
    val scroll = rememberScrollState()
    Column(
        Modifier.fillMaxSize().card(RoundedCornerShape(24.px), borderWidth = 1.px).padding(40.px)
            .focusable().verticalScroll(scroll),
        verticalArrangement = Arrangement.spacedBy(20.px),
    ) {
        Text(main, style = body(28, 500).copy(lineHeight = body(28).fontSize * 1.5f))
        extra.forEach { Text(it, style = body(24, 400, Sanga.Text2).copy(lineHeight = body(24).fontSize * 1.5f)) }
    }
}

@Composable
private fun FacilityGrid(items: List<FacilityDto>) {
    if (items.isEmpty()) {
        Text("—", style = body(24, 400, Sanga.Text3))
        return
    }
    LazyVerticalGrid(
        columns = GridCells.Fixed(2),
        horizontalArrangement = Arrangement.spacedBy(24.px),
        verticalArrangement = Arrangement.spacedBy(24.px),
    ) {
        items(items, key = { it.id }) { f ->
            Focusable(onClick = {}) { focused ->
                val shape = RoundedCornerShape(24.px)
                Column(
                    Modifier.fillMaxWidth()
                        .card(shape, borderColor = if (focused) Sanga.Accent else Sanga.Border, borderWidth = if (focused) 3.px else 1.px)
                        .clip(shape),
                ) {
                    // Photo-first card; the name sits on the image over a soft gradient.
                    Box(Modifier.fillMaxWidth().height(230.px)) {
                        if (f.imageUrl != null) {
                            AsyncImage(f.imageUrl, null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                        } else {
                            val (from, to) = if (f.category == "dining") Color(0xFF5A3A1E) to Color(0xFF0C2B45) else Color(0xFF0E5C78) to Color(0xFF0C2B45)
                            Box(Modifier.fillMaxSize().background(Brush.linearGradient(listOf(from, to))), contentAlignment = Alignment.Center) {
                                SIcon(if (f.category == "dining") SIcons.RoomService else SIcons.Facilities, Color.White.copy(alpha = 0.35f), 96.px)
                            }
                        }
                        Box(Modifier.matchParentSize().background(Brush.verticalGradient(0.45f to Color.Transparent, 1f to Color(0xE6061A2B))))
                        Text(
                            f.name, style = body(30, 800), maxLines = 1, overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.align(Alignment.BottomStart).padding(horizontal = 28.px, vertical = 18.px),
                        )
                    }
                    Column(Modifier.padding(horizontal = 28.px, vertical = 20.px), verticalArrangement = Arrangement.spacedBy(8.px)) {
                        Text(listOfNotNull(f.location, f.hours).joinToString("  ·  ").ifEmpty { " " }, style = body(21, 600, Sanga.Accent), maxLines = 1, overflow = TextOverflow.Ellipsis)
                        f.description?.let { Text(it, style = body(19, 400, Sanga.Text2), maxLines = 2, overflow = TextOverflow.Ellipsis) }
                    }
                }
            }
        }
    }
}

@Composable
private fun GalleryGrid(photos: List<GalleryDto>, onOpen: (Int) -> Unit) {
    if (photos.isEmpty()) {
        Text(stringResource(R.string.gallery_empty), style = body(24, 400, Sanga.Text3))
        return
    }
    LazyVerticalGrid(
        columns = GridCells.Fixed(3),
        horizontalArrangement = Arrangement.spacedBy(20.px),
        verticalArrangement = Arrangement.spacedBy(20.px),
    ) {
        items(photos.size, key = { photos[it].id }) { i ->
            val p = photos[i]
            Focusable(onClick = { onOpen(i) }) { focused ->
                val shape = RoundedCornerShape(18.px)
                Box(
                    Modifier.fillMaxWidth().aspectRatio(16f / 10f).clip(shape)
                        .border(if (focused) 4.px else 1.px, if (focused) Sanga.Accent else Sanga.Border, shape)
                        .background(Sanga.Card),
                ) {
                    AsyncImage(p.thumbUrl, p.title, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
                    val label = p.title ?: p.album
                    if (label != null) {
                        Box(Modifier.matchParentSize().background(Brush.verticalGradient(0.55f to Color.Transparent, 1f to Color(0xCC061A2B))))
                        Text(label, style = body(20, 700), maxLines = 1, overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.align(Alignment.BottomStart).padding(horizontal = 18.px, vertical = 12.px))
                    }
                }
            }
        }
    }
}

/** Full-screen photo viewer: ◀ ▶ to browse, BACK to close. */
@Composable
private fun GalleryViewer(photos: List<GalleryDto>, start: Int, onClose: () -> Unit) {
    if (photos.isEmpty()) return
    var index by remember { mutableStateOf(start.coerceIn(0, photos.lastIndex)) }
    val focus = remember { FocusRequester() }
    LaunchedEffect(Unit) { runCatching { focus.requestFocus() } }
    androidx.activity.compose.BackHandler(onBack = onClose)
    val p = photos[index]
    Box(
        Modifier.fillMaxSize().background(Color(0xF2020A12))
            .focusRequester(focus)
            .onPreviewKeyEvent { e ->
                if (e.type != androidx.compose.ui.input.key.KeyEventType.KeyDown) return@onPreviewKeyEvent false
                when (e.key) {
                    androidx.compose.ui.input.key.Key.DirectionRight -> { index = (index + 1) % photos.size; true }
                    androidx.compose.ui.input.key.Key.DirectionLeft -> { index = (index - 1 + photos.size) % photos.size; true }
                    else -> false
                }
            }
            .focusable(),
    ) {
        androidx.compose.animation.Crossfade(targetState = p.imageUrl, label = "photo") { url ->
            AsyncImage(url, p.title, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize().padding(48.px))
        }
        Box(Modifier.align(Alignment.BottomCenter).fillMaxWidth().background(Brush.verticalGradient(listOf(Color.Transparent, Color(0xE6020A12))))
            .padding(horizontal = 96.px, vertical = 40.px)) {
            Column(Modifier.align(Alignment.BottomStart), verticalArrangement = Arrangement.spacedBy(6.px)) {
                p.album?.let { Text(it.uppercase(), style = display(18, 0.16f, Sanga.Accent)) }
                p.title?.let { Text(it, style = body(32, 800)) }
                p.caption?.let { Text(it, style = body(22, 400, Sanga.Text2), modifier = Modifier.widthIn(max = 1100.px)) }
            }
            Column(Modifier.align(Alignment.BottomEnd), horizontalAlignment = Alignment.End) {
                Text("${index + 1} / ${photos.size}", style = body(26, 700))
                Text(stringResource(R.string.gallery_hint), style = body(18, 400, Sanga.Text3))
            }
        }
    }
}

@Composable
private fun WifiCard(data: Bootstrap?) {
    val wifi = data?.hotel?.wifi ?: return
    val qr = rememberQr(wifi.qrPayload)
    Column(
        Modifier.fillMaxWidth().card(RoundedCornerShape(24.px), borderWidth = 1.px).padding(36.px),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(20.px),
    ) {
        Text(stringResource(R.string.wifi_guest), style = body(28, 700))
        qr?.let { Image(it, null, Modifier.size(240.px).clip(RoundedCornerShape(16.px))) }
        Text(stringResource(R.string.wifi_name, wifi.ssid), style = body(22, 600, Sanga.Text2), textAlign = TextAlign.Center)
        Text(stringResource(R.string.wifi_scan), style = body(18, 400, Sanga.Text3), textAlign = TextAlign.Center)
    }
}

@Composable
private fun WifiBig(data: Bootstrap?) {
    val wifi = data?.hotel?.wifi
    if (wifi == null) {
        Text("—", style = body(24, 400, Sanga.Text3))
        return
    }
    val qr = rememberQr(wifi.qrPayload, 640)
    Row(
        Modifier.fillMaxSize().card(RoundedCornerShape(24.px), borderWidth = 1.px).padding(56.px),
        horizontalArrangement = Arrangement.spacedBy(56.px),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        qr?.let { Image(it, null, Modifier.size(420.px).clip(RoundedCornerShape(20.px))) }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(24.px)) {
            Text(stringResource(R.string.wifi_guest).uppercase(), style = display(20, 0.16f, Sanga.Accent))
            Text(wifi.ssid, style = body(44, 800), maxLines = 2, overflow = TextOverflow.Ellipsis)
            wifi.password?.let { Text(stringResource(R.string.wifi_password, it), style = body(30, 600, Sanga.Text2)) }
            Text(stringResource(R.string.wifi_scan), style = body(24, 400, Sanga.Text3), modifier = Modifier.widthIn(max = 520.px))
        }
    }
}

@Composable
private fun ReceptionCard(data: Bootstrap?) {
    val ext = data?.hotel?.receptionExtension ?: return
    Column(
        Modifier.fillMaxWidth().card(RoundedCornerShape(24.px), borderWidth = 1.px).padding(horizontal = 32.px, vertical = 28.px),
        verticalArrangement = Arrangement.spacedBy(8.px),
    ) {
        Text(stringResource(R.string.reception_24h), style = body(20, 400, Sanga.Text3))
        Text(stringResource(R.string.reception_dial, ext), style = body(26, 700))
    }
}

