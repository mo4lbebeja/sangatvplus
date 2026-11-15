package com.sangatv.plus.ui

import android.app.Application
import android.content.Context
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.sangatv.plus.SangaApp
import com.sangatv.plus.data.local.PinGuard
import com.sangatv.plus.data.model.ChannelDto
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

enum class InfoSection { About, Facilities, Dining, Gallery, Wifi, Contact, Checkout }

sealed interface Screen {
    data object Home : Screen
    data object Tv : Screen
    data class Info(val section: InfoSection) : Screen
    data object Apps : Screen
    data object Technician : Screen
}

class AppViewModel(app: Application) : AndroidViewModel(app) {
    private val sanga = app as SangaApp
    val repo = sanga.repository
    val kiosk = sanga.kiosk

    val bootstrap = repo.bootstrap
    val channels = repo.channels
    val facilities = repo.facilities
    val online = repo.online
    val provisioned = repo.provisioned

    private val _screen = MutableStateFlow<Screen>(Screen.Home)
    val screen: StateFlow<Screen> = _screen.asStateFlow()

    private val prefs = app.getSharedPreferences("viewer", Context.MODE_PRIVATE)

    fun open(s: Screen) {
        _screen.value = s
    }

    fun goHome() = open(Screen.Home)

    // ---- Channels ----
    var lastChannelId: Long
        get() = prefs.getLong("last_channel", -1)
        set(v) = prefs.edit().putLong("last_channel", v).apply()

    /** First open after power-on → hotel's start channel (if set); otherwise the last watched one. */
    fun initialChannel(list: List<ChannelDto>): ChannelDto? {
        val start = bootstrap.value?.config?.startChannelNumber
        if (com.sangatv.plus.device.TvDefaults.freshSession) {
            com.sangatv.plus.device.TvDefaults.freshSession = false
            if (start != null) list.firstOrNull { it.number == start }?.let { return it }
        }
        return list.firstOrNull { it.id == lastChannelId } ?: list.firstOrNull()
    }

    // ---- Provisioning ----
    private val _provisionState = MutableStateFlow<ProvisionState>(ProvisionState.Idle)
    val provisionState: StateFlow<ProvisionState> = _provisionState.asStateFlow()

    fun provision(code: String) {
        if (code.isBlank()) return
        _provisionState.value = ProvisionState.Busy
        viewModelScope.launch {
            repo.provision(code)
                .onSuccess { _provisionState.value = ProvisionState.Idle; goHome() }
                .onFailure { _provisionState.value = ProvisionState.Error(it.message ?: "") }
        }
    }

    // ---- Technician ----
    suspend fun verifyPin(pin: String): PinGuard.Result = repo.verifyPin(pin)

    fun refreshNow(done: (Boolean) -> Unit) = viewModelScope.launch { done(repo.refreshAll()) }

    fun checkUpdate(done: (String) -> Unit) = viewModelScope.launch { done(repo.checkUpdateNow()) }

    fun resetRegistration() {
        repo.resetRegistration()
        goHome()
    }
}

sealed interface ProvisionState {
    data object Idle : ProvisionState
    data object Busy : ProvisionState
    data class Error(val message: String) : ProvisionState
}
