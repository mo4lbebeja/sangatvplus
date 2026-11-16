package com.sangatv.plus.device

import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.provider.Settings
import android.util.TypedValue
import android.view.Gravity
import android.view.KeyEvent
import android.view.View
import android.view.ViewGroup
import android.view.WindowManager
import android.view.animation.LinearInterpolator
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.TextView
import androidx.core.content.res.ResourcesCompat
import com.sangatv.plus.R
import com.sangatv.plus.data.Repository
import com.sangatv.plus.data.model.AnnouncementDto

/**
 * Announcements on top of other apps (YouTube, Netflix, HDMI …) while SangaTV Plus is not on screen.
 * Same rules as inside the app: ticker lines run along the bottom; a full-screen announcement shows
 * until the guest presses OK (emergencies stay until they end). Needs "Display over other apps".
 * Plain Views (no Compose) so it runs from [SangaService] without an activity.
 */
class AnnouncementOverlay(private val context: Context, private val repo: Repository) {
    private val wm = context.getSystemService(WindowManager::class.java)
    private val handler = Handler(Looper.getMainLooper())
    private val scale: Float get() = context.resources.displayMetrics.widthPixels / 1920f

    private var ticker: View? = null
    private var tickerKey: String? = null
    private var tickerAnim: ValueAnimator? = null
    private var modal: View? = null
    private var modalKey: String? = null

    /** Same language as the app UI (guest's language from the CMS, Indonesian by default), not the TV's system language. */
    private fun text(id: Int): String {
        val lang = if (repo.bootstrap.value?.guest?.language == "en") "en" else "id"
        val cfg = android.content.res.Configuration(context.resources.configuration).apply { setLocale(java.util.Locale.forLanguageTag(lang)) }
        return context.createConfigurationContext(cfg).getString(id)
    }

    private val font: Typeface? by lazy { runCatching { ResourcesCompat.getFont(context, R.font.plus_jakarta_sans) }.getOrNull() }

    private val loop = object : Runnable {
        override fun run() {
            runCatching { update() }
            handler.postDelayed(this, 1000)
        }
    }

    fun start() {
        handler.removeCallbacks(loop)
        handler.post(loop)
    }

    fun stop() {
        handler.removeCallbacks(loop)
        showTicker(emptyList())
        showModal(null)
    }

    private fun update() {
        val screenOn = context.getSystemService(PowerManager::class.java)?.isInteractive ?: true
        val allowed = screenOn && Settings.canDrawOverlays(context) && !AutoStart.isAppForeground &&
            !AutoStart.maintenance && !ServiceState.isReleased && repo.provisioned.value
        if (!allowed) {
            showTicker(emptyList())
            showModal(null)
            return
        }
        val (m, tickers) = AnnouncementDto.split(AnnouncementDto.live(repo.announcements.value), repo.dismissed.value)
        showModal(m)
        showTicker(if (m == null) tickers else emptyList())
    }

    // ---------------- Ticker ----------------

    private fun showTicker(list: List<AnnouncementDto>) {
        val key = list.joinToString("|") { it.version }.ifEmpty { null }
        if (key == tickerKey) return
        tickerAnim?.cancel()
        tickerAnim = null
        ticker?.let { runCatching { wm.removeView(it) } }
        ticker = null
        tickerKey = key
        if (list.isEmpty()) return

        val s = scale
        val top = list.firstOrNull { it.isEmergency } ?: list.firstOrNull { it.level == "warning" } ?: list.first()
        val color = levelColor(top)
        val text = list.joinToString("     ◆     ") { "${it.title}: ${it.message.replace('\n', ' ')}" }

        val bar = LinearLayout(context).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            background = GradientDrawable().apply {
                setColor(Color.argb(235, 3, 18, 32))
                setStroke((1 * s).toInt().coerceAtLeast(1), (color and 0x00FFFFFF) or 0x99000000.toInt())
            }
            setPadding(0, (9 * s).toInt(), 0, (9 * s).toInt())
        }
        val badge = label(
            text(if (top.isEmergency) R.string.announcement_emergency else R.string.announcement_label),
            17, 800, onColor(color),
        ).apply {
            background = rounded(color, 10 * s)
            setPadding((16 * s).toInt(), (6 * s).toInt(), (16 * s).toInt(), (6 * s).toInt())
        }
        bar.addView(badge, LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            marginStart = (40 * s).toInt()
            marginEnd = (24 * s).toInt()
        })

        val clip = FrameLayout(context)
        val running = label(text, 24, 600, Color.parseColor("#F2F8FB")).apply { maxLines = 1; isSingleLine = true }
        val textW = running.paint.measureText(text) + 8 * s
        clip.addView(running, FrameLayout.LayoutParams(textW.toInt(), ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.CENTER_VERTICAL or Gravity.START))
        bar.addView(clip, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f).apply { marginEnd = (40 * s).toInt() })

        val lp = WindowManager.LayoutParams(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.WRAP_CONTENT,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_NOT_TOUCHABLE or
                WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
            PixelFormat.TRANSLUCENT,
        ).apply { gravity = Gravity.BOTTOM }
        runCatching { wm.addView(bar, lp) }.onFailure { return }
        ticker = bar

        // Classic TV ticker: enters from the right edge, leaves on the left, repeats.
        clip.post {
            val boxW = clip.width.toFloat()
            if (boxW <= 0f) return@post
            if (!ValueAnimator.areAnimatorsEnabled()) { running.translationX = 0f; return@post }
            val pxPerSec = 120 * s
            tickerAnim = ValueAnimator.ofFloat(boxW, -textW).apply {
                duration = ((boxW + textW) / pxPerSec * 1000).toLong()
                interpolator = LinearInterpolator()
                repeatCount = ValueAnimator.INFINITE
                addUpdateListener { running.translationX = it.animatedValue as Float }
                start()
            }
        }
    }

    // ---------------- Full screen ----------------

    @SuppressLint("SetTextI18n")
    private fun showModal(a: AnnouncementDto?) {
        val key = a?.version
        if (key == modalKey) return
        modal?.let { runCatching { wm.removeView(it) } }
        modal = null
        modalKey = key
        if (a == null) return

        val s = scale
        val color = levelColor(a)
        val root = object : FrameLayout(context) {
            override fun dispatchKeyEvent(event: KeyEvent): Boolean {
                val volume = event.keyCode == KeyEvent.KEYCODE_VOLUME_UP || event.keyCode == KeyEvent.KEYCODE_VOLUME_DOWN ||
                    event.keyCode == KeyEvent.KEYCODE_VOLUME_MUTE
                if (volume) return false // let the TV change the volume
                val close = event.keyCode == KeyEvent.KEYCODE_DPAD_CENTER || event.keyCode == KeyEvent.KEYCODE_ENTER ||
                    event.keyCode == KeyEvent.KEYCODE_NUMPAD_ENTER || event.keyCode == KeyEvent.KEYCODE_BACK
                if (event.action == KeyEvent.ACTION_UP && close && !a.isEmergency) {
                    repo.dismissAnnouncement(a.version)
                    handler.post { runCatching { update() } }
                }
                return true // nothing reaches the app underneath while the announcement is up
            }
        }.apply {
            setBackgroundColor(Color.argb(230, 3, 18, 32))
            isFocusable = true
            isFocusableInTouchMode = true
        }

        val card = LinearLayout(context).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER_HORIZONTAL
            background = GradientDrawable().apply {
                setColor(Color.parseColor("#0B2338"))
                cornerRadius = 32 * s
                setStroke((3 * s).toInt().coerceAtLeast(1), color)
            }
            setPadding((72 * s).toInt(), (52 * s).toInt(), (72 * s).toInt(), (52 * s).toInt())
        }
        fun gap(v: View, top: Int) = card.addView(v, LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply { topMargin = (top * s).toInt() })

        gap(label(text(if (a.isEmergency) R.string.announcement_emergency else R.string.announcement_label), 20, 800, onColor(color)).apply {
            background = rounded(color, 12 * s)
            setPadding((22 * s).toInt(), (8 * s).toInt(), (22 * s).toInt(), (8 * s).toInt())
        }, 0)
        gap(label(a.title, 46, 800, Color.parseColor("#F2F8FB")).apply { gravity = Gravity.CENTER }, 26)
        gap(label(a.message, 28, 500, Color.parseColor("#CFE0EC")).apply { gravity = Gravity.CENTER; setLineSpacing(0f, 1.15f) }, 18)
        if (!a.isEmergency) {
            gap(label("OK  ·  " + text(R.string.announcement_ok), 20, 600, Color.parseColor("#8FB3C9")), 30)
        }

        root.addView(card, FrameLayout.LayoutParams((1200 * s).toInt(), ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.CENTER))

        val lp = WindowManager.LayoutParams(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
            PixelFormat.TRANSLUCENT,
        )
        runCatching { wm.addView(root, lp) }.onFailure { return }
        modal = root
        root.requestFocus()
    }

    // ---------------- helpers ----------------

    private fun label(text: String, size: Int, weight: Int, color: Int) = TextView(context).apply {
        this.text = text
        setTextColor(color)
        setTextSize(TypedValue.COMPLEX_UNIT_PX, size * scale)
        typeface = Typeface.create(font ?: Typeface.DEFAULT, weight, false)
        includeFontPadding = false
    }

    private fun rounded(color: Int, radius: Float) = GradientDrawable().apply {
        setColor(color)
        cornerRadius = radius
    }

    private fun levelColor(a: AnnouncementDto): Int = when (a.level) {
        "emergency" -> Color.parseColor("#C62828")
        "warning" -> Color.parseColor("#B7791F")
        else -> Color.parseColor("#38C6D9")
    }

    private fun onColor(color: Int): Int = if (color == Color.parseColor("#38C6D9")) Color.parseColor("#04232D") else Color.WHITE
}
