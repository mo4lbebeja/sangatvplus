package com.sangatv.plus.ui.components

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.unit.dp

/** Stroke icons from the mockup (24×24 viewBox, 1.6 stroke). Tint with ColorFilter. */
object SIcons {
    private fun icon(name: String, stroke: Float = 1.6f, vararg paths: String): ImageVector =
        ImageVector.Builder(name, 24.dp, 24.dp, 24f, 24f).apply {
            paths.forEach {
                addPath(
                    pathData = PathParser().parsePathString(it).toNodes(),
                    stroke = SolidColor(Color.White),
                    strokeLineWidth = stroke,
                    strokeLineCap = StrokeCap.Round,
                    strokeLineJoin = StrokeJoin.Round,
                )
            }
        }.build()

    private fun circle(cx: Float, cy: Float, r: Float) = "M${cx - r} ${cy}a$r $r 0 1 0 ${2 * r} 0a$r $r 0 1 0 ${-2 * r} 0"

    val Home = icon("home", 1.6f, "M3 11l9-7 9 7", "M5 10v10h14V10", "M10 20v-5h4v5")
    val Tv = icon("tv", 1.6f, "M4 5h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z", "M8 21h8M12 18v3M9 2l3 3 3-3")
    val Info = icon("info", 1.6f, circle(12f, 12f, 9f), "M12 16v-5M12 8h.01")
    val RoomService = icon("roomservice", 1.6f, "M4 16h16", "M6 16a6 6 0 0 1 12 0", "M12 8V6M3 19h18")
    val Facilities = icon("facilities", 1.6f, "M2 19c2 0 2-1.5 4-1.5s2 1.5 4 1.5 2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5", "M7 15V6a2 2 0 0 1 4 0M13 15V6a2 2 0 0 1 4 0M7 9h6M7 12h6")
    val Apps = icon("apps", 1.6f, "M4.5 3h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1-1.5 1.5h-4A1.5 1.5 0 0 1 3 8.5v-4A1.5 1.5 0 0 1 4.5 3z", "M15.5 3h4A1.5 1.5 0 0 1 21 4.5v4a1.5 1.5 0 0 1-1.5 1.5h-4A1.5 1.5 0 0 1 14 8.5v-4A1.5 1.5 0 0 1 15.5 3z", "M4.5 14h4a1.5 1.5 0 0 1 1.5 1.5v4A1.5 1.5 0 0 1 8.5 21h-4A1.5 1.5 0 0 1 3 19.5v-4A1.5 1.5 0 0 1 4.5 14z", "M15.5 14h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a1.5 1.5 0 0 1-1.5-1.5v-4a1.5 1.5 0 0 1 1.5-1.5z")
    val ChevronRight = icon("chevright", 2.2f, "M9 6l6 6-6 6")
    val ChevronLeft = icon("chevleft", 2.2f, "M15 18l-6-6 6-6")
    val Clock = icon("clock", 1.6f, circle(12f, 12f, 9f), "M12 7v5l3 2")
    val Weather = icon("weather", 1.6f, circle(9f, 9f, 3.5f), "M9 2.5v1.5M3.5 9H2M4.8 4.8l1 1M13.2 4.8l-1 1", "M8 20h9a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 8 20z")
    val Lock = icon("lock", 2f, "M6 11h12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2z", "M8 11V7a4 4 0 0 1 8 0v4")
    val Hdmi = icon("hdmi", 1.6f, "M3 8h18v6l-2 3H5l-2-3V8z", "M7 11h10M9 17v2M15 17v2")
    val Offline = icon("offline", 1.8f, "M2 2l20 20", "M8.5 16.5a5 5 0 0 1 7 0", "M5 12.9a10 10 0 0 1 5.2-2.8", "M19 12.9a10 10 0 0 0-2-1.4", "M12 20h.01")
    val Star = icon("star", 1.8f, "M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z")
    val Calendar = icon("calendar", 1.6f, "M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z", "M3 10h18M8 3v4M16 3v4")
    val Speaker = icon("speaker", 1.6f, "M4 9h4l5-4v14l-5-4H4z", "M17 9a4 4 0 0 1 0 6M19.5 6.5a8 8 0 0 1 0 11")
    val Subtitles = icon("subtitles", 1.6f, "M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z", "M6 12h4M12 12h6M6 15h8M16 15h2")
    val Moon = icon("moon", 1.6f, "M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z")
    val Recall = icon("recall", 1.8f, "M4 9h11a5 5 0 0 1 0 10H8", "M8 5L4 9l4 4")
    val Megaphone = icon("megaphone", 1.6f, "M3 10v4h3l7 4V6L6 10H3z", "M17 8a5 5 0 0 1 0 8", "M7 14l1 5h2l-1-4")
}
