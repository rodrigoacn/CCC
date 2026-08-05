package com.classexpress.app.ui.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.Checkbox
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.TabRowDefaults
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.model.Language
import com.classexpress.app.ui.components.PrimaryButton
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.MintDark
import com.classexpress.app.ui.theme.PageBackground
import androidx.navigation.NavHostController

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AuthScreen(navController: NavHostController) {
    val vm: AuthViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()
    var tab by rememberSaveable { mutableStateOf(0) }

    Column(
        Modifier
            .fillMaxSize()
            .background(PageBackground)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp, vertical = 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(24.dp))
        Text("ClassExpress", fontSize = 30.sp, fontWeight = FontWeight.Black, color = Mint)
        Text("Tu plataforma de aprendizaje", fontSize = 14.sp, color = InkSecondary)

        Spacer(Modifier.height(28.dp))

        Column(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(16.dp))
                .background(Color.White)
                .border(1.dp, Hairline, RoundedCornerShape(16.dp))
                .padding(20.dp),
        ) {
            TabRow(
                selectedTabIndex = tab,
                containerColor = Color.White,
                contentColor = Mint,
                indicator = { pos ->
                    TabRowDefaults.SecondaryIndicator(
                        Modifier.tabIndicatorOffset(pos[tab]).height(2.dp),
                        color = Mint,
                    )
                },
            ) {
                Tab(selected = tab == 0, onClick = { tab = 0; vm.clearMessages() }, text = { Text("Iniciar sesión", fontWeight = FontWeight.Bold) })
                Tab(selected = tab == 1, onClick = { tab = 1; vm.clearMessages() }, text = { Text("Registrarse", fontWeight = FontWeight.Bold) })
            }

            Spacer(Modifier.height(18.dp))

            state.error?.let {
                MessageBanner(it, isError = true)
                Spacer(Modifier.height(12.dp))
            }
            state.success?.let {
                MessageBanner(it, isError = false)
                Spacer(Modifier.height(12.dp))
            }

            if (state.needsVerification) {
                VerifyPendingSection(state.registerEmail, onResend = { vm.resendVerification(state.registerEmail) }, onBackToLogin = { tab = 0 })
            } else if (tab == 0) {
                LoginContent(state.loading) { email, pass, rol -> vm.login(email, pass) }
                Row(
                    Modifier.padding(top = 16.dp),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    Text("¿No tienes una cuenta? ", fontSize = 13.sp, color = InkSecondary)
                    Text(
                        "Regístrate aquí",
                        color = Mint,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.clickable { tab = 1 },
                    )
                }
                Spacer(Modifier.height(8.dp))
                Text(
                    "¿No recibiste el correo de verificación?",
                    fontSize = 13.sp,
                    color = InkSecondary,
                    modifier = Modifier.clickable { navController.navigate("verify") }.padding(vertical = 8.dp),
                )
            } else {
                RegisterContent(
                    state = state,
                    onRegister = { n, u, e, p, c, r, pid, idi ->
                        vm.register(n, u, e, p, c, r, pid, idi)
                    },
                )
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.Center) {
                    Text("¿Ya tienes una cuenta? ", fontSize = 13.sp, color = InkSecondary)
                    Text(
                        "Inicia sesión aquí",
                        color = Mint,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.clickable { tab = 0 },
                    )
                }
            }

            Spacer(Modifier.height(12.dp))
            Text(
                "¿Olvidaste tu contraseña?",
                fontSize = 13.sp,
                color = InkSecondary,
                modifier = Modifier
                    .align(Alignment.CenterHorizontally)
                    .clickable { navController.navigate("forgot") }
                    .padding(8.dp),
            )
        }
    }
}

@Composable
fun VerifyPendingSection(email: String, onResend: () -> Unit, onBackToLogin: () -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(
            "Cuenta creada. Revisa tu correo y verifica tu cuenta antes de iniciar sesión.",
            fontSize = 14.sp,
            color = InkSecondary,
        )
        if (email.isNotBlank()) {
            Text(email, fontSize = 13.sp, color = Ink, fontWeight = FontWeight.SemiBold)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            PrimaryButton(text = "Reenviar enlace", onClick = onResend, modifier = Modifier.weight(1f))
        }
        Text(
            "Ir a iniciar sesión",
            color = Mint,
            fontWeight = FontWeight.Bold,
            fontSize = 14.sp,
            modifier = Modifier.clickable { onBackToLogin() }.align(Alignment.CenterHorizontally),
        )
    }
}

@Composable
fun MessageBanner(message: String, isError: Boolean) {
    val bg = if (isError) Color(0x22DC2626) else Color(0x2216A34A)
    val fg = if (isError) Color(0xFFDC2626) else Color(0xFF16A34A)
    Text(
        message,
        color = fg,
        fontSize = 13.sp,
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(bg)
            .border(1.dp, fg.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
            .padding(10.dp, 12.dp),
    )
}

@Composable
private fun LoginContent(loading: Boolean, onLogin: (String, String, String) -> Unit) {
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var rol by rememberSaveable { mutableStateOf("student") }
    var rememberMe by rememberSaveable { mutableStateOf(false) }
    var showPass by remember { mutableStateOf(false) }

    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
        AuthTextField(
            value = email,
            onValueChange = { email = it },
            label = "Correo electrónico",
            placeholder = "correo@ejemplo.com",
            keyboardType = KeyboardType.Email,
        )
        AuthTextField(
            value = password,
            onValueChange = { password = it },
            label = "Contraseña",
            placeholder = "••••••••",
            isPassword = true,
            showPassword = showPass,
            onTogglePassword = { showPass = !showPass },
        )

        Text("Quiero entrar como", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = InkSecondary)
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            RolePill("Estudiante", rol == "student", { rol = "student" }, Modifier.weight(1f))
            RolePill("Profesor", rol == "teacher", { rol = "teacher" }, Modifier.weight(1f))
        }

        Row(verticalAlignment = Alignment.CenterVertically) {
            Checkbox(checked = rememberMe, onCheckedChange = { rememberMe = it })
            Text("Recuérdame", fontSize = 13.sp, color = InkSecondary)
        }

        PrimaryButton(text = "Iniciar sesión", onClick = { onLogin(email, password, rol) }, loading = loading)
    }
}

@Composable
private fun RolePill(label: String, selected: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (selected) MintDark.copy(alpha = 0.18f) else Color.Transparent)
            .border(2.dp, if (selected) Mint else Hairline, RoundedCornerShape(12.dp))
            .clickable { onClick() }
            .padding(vertical = 12.dp),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            label,
            color = if (selected) MintDark else InkSecondary,
            fontWeight = FontWeight.SemiBold,
            fontSize = 14.sp,
        )
    }
}

@Composable
private fun RegisterContent(
    state: AuthUiState,
    onRegister: (String, String, String, String, String, String, Int?, List<Int>) -> Unit,
) {
    var nombre by rememberSaveable { mutableStateOf("") }
    var username by rememberSaveable { mutableStateOf("") }
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var confirm by rememberSaveable { mutableStateOf("") }
    var paisId by rememberSaveable { mutableStateOf<Int?>(null) }
    val idiomas = remember { mutableStateListOf<Int>() }
    var showPass by remember { mutableStateOf(false) }

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        AuthTextField(value = nombre, onValueChange = { nombre = it }, label = "Nombre completo", placeholder = "Tu nombre")
        AuthTextField(value = username, onValueChange = { username = it }, label = "Nombre de usuario", placeholder = "tu_usuario", supporting = "(único, letras/números/_)")

        Text("Idiomas que hablas", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = InkSecondary)
        if (state.loadingOptions) {
            Text("Cargando idiomas...", fontSize = 12.sp, color = InkSecondary)
        } else {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                state.languages.chunked(3).forEach { row ->
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        row.forEach { lang ->
                            LanguagePill(lang, idiomas.contains(lang.id)) {
                                if (idiomas.contains(lang.id)) idiomas.remove(lang.id) else idiomas.add(lang.id)
                            }
                        }
                    }
                }
            }
        }

        AuthTextField(value = email, onValueChange = { email = it }, label = "Correo electrónico", placeholder = "correo@ejemplo.com", keyboardType = KeyboardType.Email)
        AuthTextField(
            value = password,
            onValueChange = { password = it },
            label = "Contraseña",
            placeholder = "••••••••",
            supporting = "(mín. 6 caracteres)",
            isPassword = true,
            showPassword = showPass,
            onTogglePassword = { showPass = !showPass },
        )
        AuthTextField(
            value = confirm,
            onValueChange = { confirm = it },
            label = "Confirmar contraseña",
            placeholder = "••••••••",
            isPassword = true,
        )

        CountryDropdown(
            countries = state.countries,
            selectedId = paisId,
            onSelect = { paisId = it },
            loading = state.loadingOptions,
        )

        PrimaryButton(
            text = "Crear cuenta",
            onClick = { onRegister(nombre, username, email, password, confirm, "student", paisId, idiomas.toList()) },
            loading = state.loading,
        )
    }
}

@Composable
private fun LanguagePill(lang: Language, selected: Boolean, onClick: () -> Unit) {
    Text(
        lang.nombre,
        fontSize = 13.sp,
        fontWeight = FontWeight.Medium,
        color = if (selected) MintDark else Ink,
        modifier = Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(if (selected) Mint.copy(alpha = 0.18f) else Color.Transparent)
            .border(1.dp, if (selected) Mint else Hairline, RoundedCornerShape(20.dp))
            .clickable { onClick() }
            .padding(horizontal = 12.dp, vertical = 7.dp),
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CountryDropdown(
    countries: List<com.classexpress.app.model.Country>,
    selectedId: Int?,
    onSelect: (Int?) -> Unit,
    loading: Boolean,
) {
    var expanded by remember { mutableStateOf(false) }
    val selected = countries.firstOrNull { it.id == selectedId }

    Text("País (para pagos)", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = InkSecondary)

    OutlinedTextField(
        value = selected?.let { "${it.nombre} (${it.simbolo ?: ""}${it.codigoMoneda ?: ""})" } ?: "— Selecciona tu país —",
        onValueChange = {},
        readOnly = true,
        modifier = Modifier.fillMaxWidth(),
        trailingIcon = { Text("▾", color = InkSecondary) },
        enabled = !loading,
        colors = AuthFieldColors(),
        shape = RoundedCornerShape(12.dp),
    )

    DropdownMenu(
        expanded = expanded,
        onDismissRequest = { expanded = false },
        modifier = Modifier.fillMaxWidth(),
    ) {
        if (loading) {
            DropdownMenuItem(text = { Text("Cargando países...") }, onClick = {})
        } else {
            countries.forEach { c ->
                DropdownMenuItem(
                    text = { Text("${c.nombre} (${c.simbolo ?: ""}${c.codigoMoneda ?: ""})") },
                    onClick = {
                        onSelect(c.id)
                        expanded = false
                    },
                )
            }
        }
    }
}

@Composable
fun AuthTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    placeholder: String = "",
    supporting: String? = null,
    isPassword: Boolean = false,
    showPassword: Boolean = false,
    onTogglePassword: (() -> Unit)? = null,
    keyboardType: KeyboardType = KeyboardType.Text,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        placeholder = { Text(placeholder) },
        supportingText = supporting?.let { { Text(it) } },
        singleLine = true,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = AuthFieldColors(),
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        visualTransformation = if (isPassword && !showPassword) PasswordVisualTransformation() else VisualTransformation.None,
        trailingIcon = {
            if (isPassword && onTogglePassword != null) {
                IconButton(onClick = onTogglePassword) {
                    Icon(
                        if (showPassword) Icons.Filled.VisibilityOff else Icons.Filled.Visibility,
                        contentDescription = null,
                        tint = InkSecondary,
                    )
                }
            }
        },
    )
}

@Composable
fun AuthFieldColors() = OutlinedTextFieldDefaults.colors(
    focusedBorderColor = Mint,
    unfocusedBorderColor = Hairline,
    cursorColor = Mint,
    focusedLabelColor = MintDark,
)
