package com.sangatv.plus.ui.screens

import android.app.Activity
import android.view.WindowManager
import androidx.activity.compose.BackHandler
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.key.Key
import androidx.compose.ui.input.key.KeyEventType
import androidx.compose.ui.input.key.key
import androidx.compose.ui.input.key.onKeyEvent
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.input.key.type
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.media3.ui.compose.PlayerSurface
import androidx.tv.material3.Text
import com.sangatv.plus.R
import com.sangatv.plus.data.model.ChannelDto
import com.sangatv.plus.data.model.ChannelsResponse
import com.sangatv.plus.data.model.EpgResponse
import com.sangatv.plus.data.model.ProgrammeDto
import com.sangatv.plus.device.AdultLock
import com.sangatv.plus.player.PlayerController
import com.sangatv.plus.ui.components.Focusable
import com.sangatv.plus.ui.components.KeyHint
import com.sangatv.plus.ui.components.SIcon
import com.sangatv.plus.ui.components.SIcons
import com.sangatv.plus.ui.components.TimeFmt
import com.sangatv.plus.ui.components.rememberNow
import com.sangatv.plus.ui.theme.Sanga
import com.sangatv.plus.ui.theme.body
import com.sangatv.plus.ui.theme.px
import kotlinx.coroutines.delay
import java.time.Instant
import java.time.ZoneId

private const val ALL = "__all__"
private const val FAV = "__fav__"

private enum class Panel { None, List, Options, Guide }

/** Programme guide lookups for the channel UI. */
internal class Guide(private val epg: EpgResponse?, zone: String) {
    val zoneId: ZoneId = runCatching { ZoneId.of(zone) }.getOrDefault(ZoneId.of("Asia/Jakarta"))

    fun of(ch: ChannelDto): List<ProgrammeDto> = epg?.programmes?.get(ch.id.toString()).orEmpty()

    /** Running programme and the one after it. */
    fun nowNext(ch: ChannelDto, nowMs: Long): Pair<ProgrammeDto?, ProgrammeDto?> {
        val list = of(ch)
        val i = list.indexOfFirst { it.isOnAt(nowMs) }
        return if (i >= 0) list[i] to list.getOrNull(i + 1) else null to list.firstOrNull { it.startMs > nowMs }
    }

    fun upcoming(ch: ChannelDto, nowMs: Long): List<ProgrammeDto> = of(ch).filter { it.endMs > nowMs }

    fun time(ms: Long): String = Instant.ofEpochMilli(ms).atZone(zoneId).format(TimeFmt)
}

@Composable
fun ChannelScreen(
    data: ChannelsResponse?,
    zone: String,
    initial: (List<ChannelDto>) -> ChannelDto?,
    onTuned: (ChannelDto) -> Unit,
    onExit: () -> Unit,
) {
    val context = LocalContext.current
    val repo = (context.applicationContext as com.sangatv.plus.SangaApp).repository
    val controller = remember { PlayerController(context) { ch, status, msg -> repo.reportChannel(ch.id, status, msg) } }
    val status by controller.status.collectAsState()
    val epg by repo.epg.collectAsState()
    val favorites by repo.favorites.collectAsState()
    val forceNumber by repo.forceChannel.collectAsState()
    val bootstrap by repo.bootstrap.collectAsState()
    val cues by controller.cues.collectAsState()
    val announcements by repo.announcements.collectAsState()
    // Ticker bar at the bottom of the screen: lift the info bar above it.
    val bottomGap = if (announcements.isNotEmpty()) 110.px else 48.px
    val tickerInset = if (announcements.isNotEmpty()) 62.px else 0.px
    val guide = remember(epg, zone) { Guide(epg, zone) }
    val nowMs by produceState(System.currentTimeMillis()) {
        while (true) { delay(20_000); value = System.currentTimeMillis() }
    }

    val all = remember(data) { data?.channels.orEmpty().sortedWith(compareBy({ it.sortOrder }, { it.number })) }
    var current by remember { mutableStateOf<ChannelDto?>(null) }
    var previous by remember { mutableStateOf<ChannelDto?>(null) }
    var panel by remember { mutableStateOf(Panel.List) }
    var infoUntil by remember { mutableLongStateOf(0L) }
    var digits by remember { mutableStateOf("") }
    var pinFor by remember { mutableStateOf<ChannelDto?>(null) }
    var toast by remember { mutableStateOf<String?>(null) }
    var watchStart by remember { mutableLongStateOf(0L) }

    fun closeView() {
        current?.let { repo.recordView(it.id, watchStart, (System.currentTimeMillis() - watchStart) / 1000) }
    }

    // Keep the screen on while watching; release the player when leaving.
    DisposableEffect(Unit) {
        val window = (context as? Activity)?.window
        window?.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        onDispose {
            closeView()
            window?.clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
            controller.release()
        }
    }

    fun tune(ch: ChannelDto, bypassLock: Boolean = false) {
        if (ch.isAdult && !bypassLock && !AdultLock.isUnlocked) {
            pinFor = ch
            return
        }
        if (current?.id != ch.id) {
            closeView()
            previous = current
            watchStart = System.currentTimeMillis()
        }
        current = ch
        controller.play(ch)
        onTuned(ch)
        infoUntil = System.currentTimeMillis() + 5000
    }

    // First tune, and re-tune when signed URLs are refreshed.
    LaunchedEffect(all) {
        val cur = current
        val target = if (cur == null) initial(all)?.takeUnless { it.isAdult } ?: all.firstOrNull { !it.isAdult } else all.firstOrNull { it.id == cur.id }
        if (target != null) {
            if (cur == null) tune(target) else { current = target; controller.play(target) }
        }
    }

    // Announcement "pindahkan ke channel": switch once, no PIN prompt.
    LaunchedEffect(forceNumber, all) {
        val n = forceNumber ?: return@LaunchedEffect
        all.firstOrNull { it.number == n }?.let { tune(it, bypassLock = true); panel = Panel.None }
        repo.consumeForceChannel()
    }

    // Number keys: collect digits for 1.5 s, then jump.
    LaunchedEffect(digits) {
        if (digits.isEmpty()) return@LaunchedEffect
        delay(1500)
        all.firstOrNull { it.number == digits.toIntOrNull() }?.let { tune(it) }
        digits = ""
    }

    LaunchedEffect(toast) {
        if (toast != null) { delay(2200); toast = null }
    }

    var tick by remember { mutableLongStateOf(0L) }
    LaunchedEffect(infoUntil) {
        while (System.currentTimeMillis() < infoUntil) { delay(250); tick = System.currentTimeMillis() }
        tick = System.currentTimeMillis()
    }
    val showInfo = panel == Panel.List || tick < infoUntil || System.currentTimeMillis() < infoUntil

    /** Channel up/down; adult channels are skipped while locked. */
    fun step(delta: Int) {
        val pool = all.filter { !it.isAdult || AdultLock.isUnlocked || it.id == current?.id }
        if (pool.isEmpty()) return
        val i = pool.indexOfFirst { it.id == current?.id }.coerceAtLeast(0)
        tune(pool[(i + delta + pool.size) % pool.size])
    }

    fun toggleFav(ch: ChannelDto) {
        val adding = ch.id !in favorites
        repo.toggleFavorite(ch.id)
        toast = context.getString(if (adding) R.string.fav_added else R.string.fav_removed)
    }

    // BACK: closes whatever panel is open, otherwise returns to Home.
    BackHandler {
        when {
            pinFor != null -> pinFor = null
            panel != Panel.None && current != null -> panel = Panel.None
            else -> onExit()
        }
    }

    val watchFocus = remember { FocusRequester() }
    LaunchedEffect(panel, pinFor) { if (panel == Panel.None && pinFor == null) runCatching { watchFocus.requestFocus() } }

    Box(
        Modifier
            .fillMaxSize()
            .background(Sanga.Deepest)
            .onPreviewKeyEvent { e ->
                if (e.type != KeyEventType.KeyDown || pinFor != null) return@onPreviewKeyEvent false
                val d = digitOf(e.key)
                if (d != null) { digits = (digits + d).takeLast(4); true } else false
            },
    ) {
        PlayerSurface(player = controller.player, modifier = Modifier.fillMaxSize())

        // Subtitles (PlayerSurface draws video only).
        val subtitle = cues.mapNotNull { it.text?.toString() }.joinToString("\n").trim()
        if (subtitle.isNotEmpty()) {
            Text(
                subtitle, style = body(34, 600, Color.White), textAlign = TextAlign.Center,
                modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 90.px, start = 160.px, end = 160.px)
                    .background(Color.Black.copy(alpha = 0.62f), RoundedCornerShape(10.px)).padding(horizontal = 20.px, vertical = 8.px),
            )
        }

        // Watching mode: invisible focus target for the remote.
        Box(
            Modifier
                .fillMaxSize()
                .focusRequester(watchFocus)
                .onKeyEvent { e ->
                    if (panel != Panel.None || pinFor != null || e.type != KeyEventType.KeyDown) return@onKeyEvent false
                    when (e.key) {
                        Key.DirectionUp, Key.ChannelUp -> { step(+1); true }
                        Key.DirectionDown, Key.ChannelDown -> { step(-1); true }
                        Key.DirectionCenter, Key.Enter, Key.NumPadEnter, Key.DirectionLeft -> { panel = Panel.List; true }
                        Key.DirectionRight, Key.Menu -> { panel = Panel.Options; true }
                        Key.Guide, Key.ProgramRed -> { panel = Panel.Guide; true }
                        Key.Info -> { infoUntil = System.currentTimeMillis() + 5000; true }
                        Key.LastChannel -> { previous?.let { tune(it) }; true }
                        Key.ProgramYellow -> { current?.let { toggleFav(it) }; true }
                        else -> false
                    }
                }
                .focusable(),
        )

        StatusMessage(status, Modifier.align(Alignment.Center))

        if (digits.isNotEmpty()) {
            Text(
                digits, style = body(96, 800, Sanga.Accent),
                modifier = Modifier.align(Alignment.TopEnd).padding(top = 64.px, end = 96.px)
                    .background(Sanga.Background.copy(alpha = 0.85f), RoundedCornerShape(24.px)).padding(horizontal = 36.px, vertical = 12.px),
            )
        }

        AnimatedVisibility(
            visible = panel == Panel.List,
            enter = slideInHorizontally { -it } + fadeIn(),
            exit = slideOutHorizontally { -it } + fadeOut(),
        ) {
            ChannelPanel(
                data = data, all = all, current = current, zone = zone, guide = guide, nowMs = nowMs, favorites = favorites,
                onPick = { tune(it); panel = Panel.None },
                bottomInset = tickerInset,
            )
        }

        AnimatedVisibility(
            visible = showInfo && current != null && panel != Panel.Guide && panel != Panel.Options,
            enter = fadeIn(), exit = fadeOut(),
            modifier = Modifier.align(Alignment.BottomEnd),
        ) {
            current?.let {
                InfoBar(
                    it, status, guide, nowMs, isFavorite = it.id in favorites,
                    modifier = Modifier.padding(start = if (panel == Panel.List) 808.px else 48.px, end = 48.px, bottom = bottomGap),
                )
            }
        }

        AnimatedVisibility(
            visible = panel == Panel.Options,
            enter = slideInHorizontally { it } + fadeIn(),
            exit = slideOutHorizontally { it } + fadeOut(),
            modifier = Modifier.align(Alignment.CenterEnd),
        ) {
            current?.let { ch ->
                OptionsPanel(
                    channel = ch, guide = guide, nowMs = nowMs, controller = controller,
                    isFavorite = ch.id in favorites, previous = previous,
                    onGuide = { panel = Panel.Guide },
                    onToggleFavorite = { toggleFav(ch) },
                    onPrevious = { previous?.let { tune(it) }; panel = Panel.None },
                    onToast = { toast = it },
                    onClose = { panel = Panel.None },
                    bottomInset = tickerInset,
                )
            }
        }

        if (panel == Panel.Guide) {
            GuidePanel(
                all = all, current = current, guide = guide, nowMs = nowMs, favorites = favorites,
                onPick = { tune(it); panel = Panel.None },
            )
        }

        pinFor?.let { ch ->
            AdultPinDialog(
                channel = ch,
                pinHash = bootstrap?.adultPin,
                onUnlocked = { pinFor = null; tune(ch); panel = Panel.None },
                modifier = Modifier.align(Alignment.Center),
            )
        }

        toast?.let {
            Text(
                it, style = body(24, 700, Sanga.OnAccent),
                modifier = Modifier.align(Alignment.TopCenter).padding(top = 48.px)
                    .background(Sanga.Accent, RoundedCornerShape(999.px)).padding(horizontal = 32.px, vertical = 12.px),
            )
        }
    }
}

private fun digitOf(key: Key): Char? = when (key) {
    Key.Zero, Key.NumPad0 -> '0'; Key.One, Key.NumPad1 -> '1'; Key.Two, Key.NumPad2 -> '2'
    Key.Three, Key.NumPad3 -> '3'; Key.Four, Key.NumPad4 -> '4'; Key.Five, Key.NumPad5 -> '5'
    Key.Six, Key.NumPad6 -> '6'; Key.Seven, Key.NumPad7 -> '7'; Key.Eight, Key.NumPad8 -> '8'
    Key.Nine, Key.NumPad9 -> '9'; else -> null
}

@Composable
private fun StatusMessage(status: PlayerController.Status, modifier: Modifier) {
    val msg = when (status) {
        PlayerController.Status.FailingOver -> stringResource(R.string.channel_error)
        PlayerController.Status.Failed -> stringResource(R.string.channel_failed)
        else -> null
    } ?: return
    Text(
        msg, style = body(26, 600, Sanga.Text2),
        modifier = modifier.background(Sanga.Background.copy(alpha = 0.85f), RoundedCornerShape(18.px)).padding(horizontal = 32.px, vertical = 18.px),
    )
}

@Composable
private fun ChannelPanel(
    data: ChannelsResponse?,
    all: List<ChannelDto>,
    current: ChannelDto?,
    zone: String,
    guide: Guide,
    nowMs: Long,
    favorites: List<Long>,
    onPick: (ChannelDto) -> Unit,
    bottomInset: androidx.compose.ui.unit.Dp = 0.px,
) {
    val now by rememberNow(zone)
    val used = remember(all) { all.mapNotNull { it.categorySlug }.toSet() }
    val favName = stringResource(R.string.cat_favorites)
    val hasFav = favorites.any { id -> all.any { it.id == id } }
    val cats = remember(data, used, hasFav) {
        buildList {
            add(ALL to "")
            if (hasFav) add(FAV to favName)
            addAll(data?.categories.orEmpty().filter { it.slug in used }.map { it.slug to it.name })
        }
    }
    var cat by remember { mutableStateOf(ALL) }
    if (cats.none { it.first == cat }) cat = ALL
    val list = remember(all, cat, favorites) {
        when (cat) {
            ALL -> all
            FAV -> favorites.mapNotNull { id -> all.firstOrNull { it.id == id } }
            else -> all.filter { it.categorySlug == cat }
        }
    }

    fun moveCat(delta: Int) {
        val i = cats.indexOfFirst { it.first == cat }
        cat = cats[(i + delta + cats.size) % cats.size].first
    }

    val listState = rememberLazyListState()
    val focusReqs = remember(list) { List(list.size) { FocusRequester() } }
    val playingKnown = current != null
    LaunchedEffect(list, playingKnown) {
        val idx = list.indexOfFirst { it.id == current?.id }.coerceAtLeast(0)
        if (list.isNotEmpty()) {
            listState.scrollToItem(idx)
            delay(50)
            runCatching { focusReqs[idx].requestFocus() }
        }
    }

    Column(
        Modifier
            .width(760.px)
            .fillMaxHeight()
            .background(Sanga.Background.copy(alpha = 0.94f))
            .padding(start = 72.px, top = 56.px, end = 48.px, bottom = 40.px + bottomInset)
            .onPreviewKeyEvent { e ->
                if (e.type != KeyEventType.KeyDown) return@onPreviewKeyEvent false
                when (e.key) {
                    Key.DirectionLeft -> { moveCat(-1); true }
                    Key.DirectionRight -> { moveCat(+1); true }
                    else -> false
                }
            },
        verticalArrangement = Arrangement.spacedBy(28.px),
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(52.px).background(Sanga.Card, RoundedCornerShape(14.px)),
                contentAlignment = Alignment.Center,
            ) { SIcon(SIcons.ChevronLeft, Sanga.Text2, 26.px) }
            Text(stringResource(R.string.channel_list), style = body(38, 800), modifier = Modifier.padding(start = 16.px).weight(1f))
            Text(now.format(TimeFmt), style = body(30, 700, Sanga.Text2))
        }

        @OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
        androidx.compose.foundation.layout.FlowRow(
            horizontalArrangement = Arrangement.spacedBy(12.px),
            verticalArrangement = Arrangement.spacedBy(10.px),
        ) {
            cats.forEach { (slug, name) ->
                val on = slug == cat
                Row(
                    Modifier
                        .background(if (on) Sanga.BarLight else Color.Transparent, RoundedCornerShape(999.px))
                        .border(1.px, if (on) Sanga.BarLight else Sanga.Border2, RoundedCornerShape(999.px))
                        .padding(horizontal = 22.px, vertical = 10.px),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.px),
                ) {
                    if (slug == FAV) SIcon(SIcons.Star, if (on) Sanga.Background else Gold, 20.px)
                    Text(
                        if (slug == ALL) stringResource(R.string.cat_all) else name,
                        style = body(20, 600, if (on) Sanga.Background else Sanga.Text2),
                        maxLines = 1,
                    )
                }
            }
        }

        if (list.isEmpty()) {
            Text(stringResource(R.string.no_channels), style = body(24, 500, Sanga.Text3), modifier = Modifier.weight(1f))
        } else {
            LazyColumn(state = listState, modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.px)) {
                itemsIndexed(list, key = { _, c -> c.id }) { i, ch ->
                    Focusable(onClick = { onPick(ch) }, scaleOnFocus = false, modifier = Modifier.focusRequester(focusReqs[i])) { focused ->
                        ChannelRow(ch, focused, playing = ch.id == current?.id, now = guide.nowNext(ch, nowMs).first, nowMs = nowMs, favorite = ch.id in favorites)
                    }
                }
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(24.px)) {
            KeyHint("▲ ▼", stringResource(R.string.hint_select))
            KeyHint("◀ ▶", stringResource(R.string.hint_category))
            KeyHint("OK", stringResource(R.string.hint_watch))
            KeyHint("BACK", stringResource(R.string.hint_close))
        }
    }
}

internal val Gold = Color(0xFFD6BA80)

@Composable
private fun ChannelRow(ch: ChannelDto, focused: Boolean, playing: Boolean, now: ProgrammeDto?, nowMs: Long, favorite: Boolean) {
    val fg = if (focused) Sanga.OnAccent else Sanga.Text
    val sub = if (focused) Sanga.OnAccent.copy(alpha = 0.78f) else Sanga.Text3
    Row(
        Modifier
            .fillMaxWidth()
            .background(if (focused) Sanga.Accent else if (playing) Sanga.Card else Color.Transparent, RoundedCornerShape(18.px))
            .padding(horizontal = 22.px, vertical = 14.px),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(24.px),
    ) {
        Text("%03d".format(ch.number), style = body(26, 800, if (focused) Sanga.OnAccent else Sanga.Text3), modifier = Modifier.widthIn(min = 60.px))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(5.px)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.px)) {
                Text(ch.name, style = body(25, 700, fg), maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                if (favorite) SIcon(SIcons.Star, if (focused) Sanga.OnAccent else Gold, 20.px)
                if (ch.isAdult) SIcon(SIcons.Lock, if (focused) Sanga.OnAccent else Sanga.Error, 18.px)
            }
            if (now != null) {
                Text(now.title, style = body(17, 500, sub), maxLines = 1, overflow = TextOverflow.Ellipsis)
                ProgressLine(now.progress(nowMs), if (focused) Sanga.OnAccent else Sanga.Accent, if (focused) Sanga.OnAccent.copy(alpha = 0.25f) else Sanga.Border)
            } else {
                Text(ch.category ?: "", style = body(17, 400, sub), maxLines = 1)
            }
        }
        val badge = if (ch.isProvider) Sanga.Accent else Color(0xFF3E6C90)
        Text(
            stringResource(if (ch.isProvider) R.string.src_provider else R.string.src_local),
            style = body(15, 700, if (focused) Sanga.OnAccent else if (ch.isProvider) Sanga.Accent else Sanga.Text2),
            modifier = Modifier
                .border(1.px, if (focused) Sanga.OnAccent.copy(alpha = 0.5f) else badge, RoundedCornerShape(8.px))
                .padding(horizontal = 12.px, vertical = 5.px),
        )
    }
}

@Composable
internal fun ProgressLine(fraction: Float, color: Color, track: Color, modifier: Modifier = Modifier) {
    Box(modifier.fillMaxWidth().height(4.px).background(track, RoundedCornerShape(2.px))) {
        Box(Modifier.fillMaxWidth(fraction.coerceIn(0f, 1f)).height(4.px).background(color, RoundedCornerShape(2.px)))
    }
}

@Composable
private fun InfoBar(ch: ChannelDto, status: PlayerController.Status, guide: Guide, nowMs: Long, isFavorite: Boolean, modifier: Modifier) {
    val (now, next) = guide.nowNext(ch, nowMs)
    Row(
        modifier
            .fillMaxWidth()
            .background(Sanga.Background.copy(alpha = 0.92f), RoundedCornerShape(24.px))
            .border(1.px, Sanga.Surface2, RoundedCornerShape(24.px))
            .padding(horizontal = 36.px, vertical = 26.px),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(32.px),
    ) {
        Text("%03d".format(ch.number), style = body(56, 800, Sanga.Accent))
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.px)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.px)) {
                Text(ch.name, style = body(32, 800), maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                if (isFavorite) SIcon(SIcons.Star, Gold, 26.px)
                Text(
                    stringResource(if (ch.isProvider) R.string.src_provider_long else R.string.src_local_long),
                    style = body(16, 700, Sanga.Text2),
                    modifier = Modifier.background(Sanga.Surface2, RoundedCornerShape(8.px)).padding(horizontal = 12.px, vertical = 4.px),
                )
            }
            when {
                status == PlayerController.Status.Buffering -> Text(stringResource(R.string.loading), style = body(20, 400, Sanga.Text3))
                now != null -> {
                    Row(horizontalArrangement = Arrangement.spacedBy(14.px), verticalAlignment = Alignment.CenterVertically) {
                        Text(stringResource(R.string.now_label).uppercase(), style = body(15, 800, Sanga.Accent))
                        Text(now.title, style = body(22, 700, Sanga.Text), maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                        Text("${guide.time(now.startMs)}–${guide.time(now.endMs)}", style = body(18, 500, Sanga.Text3))
                    }
                    ProgressLine(now.progress(nowMs), Sanga.Accent, Sanga.Border, Modifier.widthIn(max = 900.px))
                    next?.let {
                        Text("${stringResource(R.string.next_label)} ${guide.time(it.startMs)} · ${it.title}", style = body(18, 400, Sanga.Text3), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
                else -> Text(ch.category ?: "", style = body(20, 400, Sanga.Text3))
            }
        }
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(8.px)) {
            KeyHint("▶", stringResource(R.string.hint_options))
        }
    }
}
