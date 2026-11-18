package com.sangatv.plus.ui.theme

import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.ExperimentalTextApi
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontVariation
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.tv.material3.MaterialTheme
import androidx.tv.material3.darkColorScheme
import com.sangatv.plus.R

/** Colours of one TV theme. */
data class Palette(
    val deepest: Color, val background: Color, val panel: Color, val card: Color, val surface2: Color,
    val border: Color, val border2: Color, val accent: Color, val onAccent: Color, val navyActive: Color,
    val barLight: Color, val barText: Color, val barMuted: Color,
    val text: Color, val text2: Color, val text3: Color, val error: Color = Color(0xFFF08A7E),
)

/** Beranda design of a theme. */
enum class HomeLayout { Ocean, Royal, Tropical, Sunset, Heritage, Sunda }

/**
 * A theme chosen per hotel in CMS → Profil Hotel → Tema TV (config.theme in the bootstrap).
 * [homeInset] keeps the announcement ticker clear of the Beranda menu (design px from the bottom).
 */
data class TvTheme(val key: String, val layout: HomeLayout, val palette: Palette, val display: FontFamily, val homeInset: Int)

/** Current theme; every screen reads its colours through this object, so a change recolours the whole app. */
object Sanga {
    var theme by mutableStateOf(TvThemes.Ocean)
        private set

    fun use(key: String?) {
        theme = TvThemes.of(key)
    }

    val Deepest get() = theme.palette.deepest
    val Background get() = theme.palette.background
    val Panel get() = theme.palette.panel
    val Card get() = theme.palette.card
    val Surface2 get() = theme.palette.surface2
    val Border get() = theme.palette.border
    val Border2 get() = theme.palette.border2
    val Accent get() = theme.palette.accent
    val OnAccent get() = theme.palette.onAccent
    val NavyActive get() = theme.palette.navyActive
    val BarLight get() = theme.palette.barLight
    val BarText get() = theme.palette.barText
    val BarMuted get() = theme.palette.barMuted
    val Text get() = theme.palette.text
    val Text2 get() = theme.palette.text2
    val Text3 get() = theme.palette.text3
    val Error get() = theme.palette.error
}

val Michroma = FontFamily(Font(R.font.michroma))

@OptIn(ExperimentalTextApi::class)
private fun jakarta(weight: Int) = Font(
    R.font.plus_jakarta_sans,
    weight = FontWeight(weight),
    variationSettings = FontVariation.Settings(FontVariation.weight(weight)),
)

val Jakarta = FontFamily(jakarta(300), jakarta(400), jakarta(500), jakarta(600), jakarta(700), jakarta(800))

@OptIn(ExperimentalTextApi::class)
private fun playfair(weight: Int) = Font(
    R.font.playfair_display,
    weight = FontWeight(weight),
    variationSettings = FontVariation.Settings(FontVariation.weight(weight)),
)

val Playfair = FontFamily(playfair(400), playfair(500), playfair(600), playfair(700))

object TvThemes {
    private fun c(v: Long) = Color(v)

    /** "Biru pantai" (doc §7): the original design. */
    val Ocean = TvTheme("ocean", HomeLayout.Ocean, Palette(
        c(0xFF031220), c(0xFF061A2B), c(0xFF08223A), c(0xFF0C2B45), c(0xFF123756), c(0xFF1A4366), c(0xFF2C5A80),
        c(0xFF38C6D9), c(0xFF04232D), c(0xFF0B3D66), c(0xFFF2F8FB), c(0xFF0B2C47), c(0xFF3E5F7A),
        c(0xFFF2F8FB), c(0xFFCFE0EC), c(0xFF9DB9CF),
    ), Michroma, 150)

    val Royal = TvTheme("royal", HomeLayout.Royal, Palette(
        c(0xFF0B0906), c(0xFF120E09), c(0xFF1A140C), c(0xFF231A10), c(0xFF2F2416), c(0xFF3D2F1C), c(0xFF5C4727),
        c(0xFFD9B56A), c(0xFF1F1606), c(0xFF3A2A12), c(0xFFF6EFE0), c(0xFF2A1E0C), c(0xFF6E5A38),
        c(0xFFF7F1E3), c(0xFFE2D6BE), c(0xFFB3A384),
    ), Playfair, 0)

    val Tropical = TvTheme("tropical", HomeLayout.Tropical, Palette(
        c(0xFF06140F), c(0xFF0A1F18), c(0xFF0E2A20), c(0xFF13352A), c(0xFF1A4437), c(0xFF235445), c(0xFF34705C),
        c(0xFF8FD694), c(0xFF08261A), c(0xFF14503D), c(0xFFF3F7EC), c(0xFF12352A), c(0xFF4C6B5E),
        c(0xFFF3F8EF), c(0xFFD3E3D4), c(0xFFA2BDA9),
    ), Jakarta, 300)

    val Sunset = TvTheme("sunset", HomeLayout.Sunset, Palette(
        c(0xFF170B0A), c(0xFF221110), c(0xFF2E1815), c(0xFF3B201B), c(0xFF4B2A22), c(0xFF5E362B), c(0xFF7F4C3B),
        c(0xFFF2A65A), c(0xFF2B1405), c(0xFF7A3B26), c(0xFFFBF1E6), c(0xFF40200F), c(0xFF86604A),
        c(0xFFFBF2E8), c(0xFFEBD6C4), c(0xFFC4A48F),
    ), Jakarta, 350)

    val Heritage = TvTheme("heritage", HomeLayout.Heritage, Palette(
        c(0xFF0D0E2B), c(0xFF171A45), c(0xFF1E2152), c(0xFF262A63), c(0xFF323676), c(0xFF3F4388), c(0xFF5A5FA8),
        c(0xFFE0A93F), c(0xFF241407), c(0xFF7A2E1D), c(0xFFF4EBDD), c(0xFF2E2119), c(0xFF72604F),
        c(0xFFF6EEE2), c(0xFFDAD6E6), c(0xFFA9A8C8),
    ), Playfair, 190)

    /** Daerah: Pasundan (West Java). Soga brown, bamboo gold and the ivory "gumading" of batik Garutan. */
    val Sunda = TvTheme("sunda", HomeLayout.Sunda, Palette(
        c(0xFF1A1009), c(0xFF2A1A0E), c(0xFF352212), c(0xFF432C17), c(0xFF54381E), c(0xFF6A4A28), c(0xFF8A6638),
        c(0xFFE2B04A), c(0xFF2A1705), c(0xFF4F7A3A), c(0xFFF3E6C8), c(0xFF2E1C0C), c(0xFF7A6040),
        c(0xFFF8EFDC), c(0xFFE6D6B8), c(0xFFBBA482),
    ), Playfair, 232)

    private val all = listOf(Ocean, Royal, Tropical, Sunset, Heritage, Sunda)

    /** Unknown keys (a newer server) fall back to the original design. */
    fun of(key: String?): TvTheme = all.firstOrNull { it.key == key } ?: Ocean
}

/**
 * Designs are drawn at 1920×1080 px. LocalScale converts design px to dp for the
 * actual screen, so layouts match the mockup on any TV density.
 */
val LocalScale = staticCompositionLocalOf { 0.5f }

val Int.px: Dp
    @Composable @ReadOnlyComposable get() = (this * LocalScale.current).dp

val Int.tx: TextUnit
    @Composable @ReadOnlyComposable get() = with(LocalDensity.current) { (this@tx * LocalScale.current).dp.toSp() }

@Composable
fun SangaTheme(content: @Composable () -> Unit) {
    val scale = LocalConfiguration.current.screenWidthDp / 1920f
    CompositionLocalProvider(LocalScale provides scale) {
        MaterialTheme(
            colorScheme = darkColorScheme(
                primary = Sanga.Accent,
                onPrimary = Sanga.OnAccent,
                background = Sanga.Background,
                surface = Sanga.Card,
                onSurface = Sanga.Text,
                error = Sanga.Error,
            ),
            content = content,
        )
    }
}

@Composable
@ReadOnlyComposable
fun body(size: Int, weight: Int = 400, color: Color = Sanga.Text) =
    TextStyle(fontFamily = Jakarta, fontWeight = FontWeight(weight), fontSize = size.tx, color = color)

@Composable
@ReadOnlyComposable
fun display(size: Int, spacing: Float = 0.1f, color: Color = Sanga.Text) =
    TextStyle(fontFamily = Sanga.theme.display, fontSize = size.tx, letterSpacing = spacing.em, color = color)
