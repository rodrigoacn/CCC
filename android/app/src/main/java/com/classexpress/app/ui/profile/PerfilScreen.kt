package com.classexpress.app.ui.profile

import android.graphics.BitmapFactory
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AdminPanelSettings
import androidx.compose.material.icons.filled.CreditCard
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Language
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material.icons.filled.SwapHoriz
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.classexpress.app.ui.components.Avatar
import com.classexpress.app.ui.components.Chip
import com.classexpress.app.ui.components.ErrorView
import com.classexpress.app.ui.components.LoadingView
import com.classexpress.app.ui.components.SectionHeader
import com.classexpress.app.ui.theme.Danger
import com.classexpress.app.ui.theme.Hairline
import com.classexpress.app.ui.theme.Ink
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import java.io.ByteArrayOutputStream
import java.util.Base64

@Composable
fun PerfilScreen(
    onOpenDashboard: () -> Unit = {},
    onOpenAdmin: () -> Unit = {},
) {
    val vm: PerfilViewModel = androidx.lifecycle.viewmodel.compose.viewModel()
    val state by vm.state.collectAsStateWithLifecycle()
    val context = LocalContext.current

    var showLangDialog by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf(false) }
    var showSwitchDialog by remember { mutableStateOf(false) }

    val avatarLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent(),
    ) { uri: Uri? ->
        if (uri != null) {
            val bmp = runCatching {
                context.contentResolver.openInputStream(uri)?.use {
                    BitmapFactory.decodeStream(it)
                }
            }.getOrNull() ?: return@rememberLauncherForActivityResult
            val scaled = if (bmp.width > 512) {
                val ratio = 512f / bmp.width
                android.graphics.Bitmap.createScaledBitmap(
                    bmp,
                    512,
                    (bmp.height * ratio).toInt(),
                    true,
                )
            } else bmp
            val bytes = ByteArrayOutputStream()
            scaled.compress(android.graphics.Bitmap.CompressFormat.JPEG, 85, bytes)
            val base64 = if (android.os.Build.VERSION.SDK_INT >= 26) {
                Base64.getEncoder().encodeToString(bytes.toByteArray())
            } else {
                android.util.Base64.encodeToString(bytes.toByteArray(), android.util.Base64.NO_WRAP)
            }
            vm.changeAvatar(base64)
        }
    }

    LaunchedEffect(Unit) { vm.load() }

    Column(Modifier.fillMaxSize().background(PageBackground)) {
        when {
            state.loading -> LoadingView(Modifier.fillMaxSize())
            state.error != null -> ErrorView(state.error!!, Modifier.fillMaxSize(), onRetry = { vm.load() })
            state.user != null -> LazyColumn(
                Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                item {
                    ProfileHeader(
                        nombre = state.user!!.nombre,
                        username = state.user!!.username,
                        email = state.user!!.email,
                        creditos = state.user!!.creditos,
                        avatar = state.user!!.avatar,
                        onEditAvatar = { avatarLauncher.launch("image/*") },
                    )
                }

                item {
                    SectionHeader("Idiomas que hablas", Modifier.padding(horizontal = 20.dp))
                }
                item {
                    Row(
                        Modifier.padding(horizontal = 20.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        state.languages.forEach { lang ->
                            Chip(
                                text = lang.nombre,
                                selected = lang.id in state.selectedLanguages,
                                onClick = { vm.toggleLanguage(lang.id) },
                            )
                        }
                    }
                }

                item {
                    SectionHeader("Preferencias", Modifier.padding(horizontal = 20.dp))
                }
                item {
                    OptionRow(
                        icon = Icons.Filled.Language,
                        label = "Idioma de la app",
                        subtitle = "Elige el idioma de tu interfaz",
                        onClick = { showLangDialog = true },
                    )
                }
                item {
                    OptionRow(
                        icon = Icons.Filled.CreditCard,
                        label = "Cambiar avatar",
                        subtitle = "Selecciona una foto de tu galería",
                        onClick = { avatarLauncher.launch("image/*") },
                    )
                }

                if (state.user?.isTeacher() == true) {
                    item {
                        SectionHeader("Profesor", Modifier.padding(horizontal = 20.dp))
                    }
                    item {
                        OptionRow(
                            icon = Icons.Filled.SwapHoriz,
                            label = if (state.modo == "teacher") "Modo: Profesor" else "Modo: Estudiante",
                            subtitle = "Cambiar a modo ${if (state.modo == "teacher") "estudiante" else "profesor"}",
                            onClick = { showSwitchDialog = true },
                        )
                    }
                    item {
                        OptionRow(
                            icon = Icons.Filled.Dashboard,
                            label = "Panel del profesor",
                            subtitle = "Crea clases y gestiona tus salas",
                            onClick = onOpenDashboard,
                        )
                    }
                }

                if (state.user?.rol == "admin") {
                    item {
                        SectionHeader("Administración", Modifier.padding(horizontal = 20.dp))
                    }
                    item {
                        OptionRow(
                            icon = Icons.Filled.AdminPanelSettings,
                            label = "Panel de administración",
                            subtitle = "Revisa y aprueba retiros",
                            onClick = onOpenAdmin,
                        )
                    }
                }

                item {
                    SectionHeader("Cuenta", Modifier.padding(horizontal = 20.dp))
                }
                item {
                    OptionRow(
                        icon = Icons.Filled.Logout,
                        label = "Cerrar sesión",
                        subtitle = "Vuelve cuando quieras",
                        onClick = { vm.logout() },
                    )
                }
                item {
                    OptionRow(
                        icon = Icons.Filled.Delete,
                        label = "Eliminar cuenta",
                        subtitle = "Esta acción no se puede deshacer",
                        danger = true,
                        onClick = { showDeleteDialog = true },
                    )
                }

                item { Spacer(Modifier.height(24.dp)) }
            }
        }
    }

    if (showLangDialog) {
        LanguageDialog(
            languages = state.languages,
            onSelect = { id ->
                vm.setAppLanguage(id)
                showLangDialog = false
            },
            onDismiss = { showLangDialog = false },
        )
    }

    if (showDeleteDialog) {
        DeleteAccountDialog(
            deleting = state.deleting,
            error = state.deleteError,
            onConfirm = { password ->
                vm.deleteAccount(password)
            },
            onDismiss = { showDeleteDialog = false },
        )
    }

    if (showSwitchDialog) {
        SwitchRoleDialog(
            switching = state.switching,
            error = state.switchError,
            current = state.modo,
            onConfirm = { password ->
                vm.switchRole(password)
            },
            onDismiss = { showSwitchDialog = false },
        )
    }
}

@Composable
private fun SwitchRoleDialog(
    switching: Boolean,
    error: String?,
    current: String,
    onConfirm: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    var password by remember { mutableStateOf("") }
    val target = if (current == "teacher") "estudiante" else "profesor"

    AlertDialog(
        onDismissRequest = { if (!switching) onDismiss() },
        title = { Text("Cambiar a modo $target") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "Escribe tu contraseña para confirmar. Podrás cambiar de rol una vez cada 24 horas.",
                    fontSize = 14.sp,
                    color = InkSecondary,
                )
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    placeholder = { Text("Contraseña") },
                    singleLine = true,
                )
                if (error != null) {
                    Text(error, fontSize = 13.sp, color = Danger)
                }
            }
        },
        confirmButton = {
            TextButton(
                enabled = password.isNotBlank() && !switching,
                onClick = { onConfirm(password) },
            ) {
                Text(if (switching) "Cambiando…" else "Cambiar", color = Mint)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !switching) {
                Text("Cancelar", color = InkSecondary)
            }
        },
    )
}

@Composable
private fun ProfileHeader(
    nombre: String,
    username: String?,
    email: String,
    creditos: Int,
    avatar: String?,
    onEditAvatar: () -> Unit,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .background(Color.White)
            .padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Box {
            Avatar(avatar, nombre, size = 96)
            Box(
                Modifier
                    .align(Alignment.BottomEnd)
                    .size(30.dp)
                    .clip(CircleShape)
                    .background(Mint)
                    .clickable(onClick = onEditAvatar),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.Edit, contentDescription = "Editar avatar", tint = Color.White, modifier = Modifier.size(16.dp))
            }
        }
        Text(nombre, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Ink)
        Text(
            listOfNotNull(username?.let { "@$it" }, email).joinToString(" · "),
            fontSize = 13.sp,
            color = InkSecondary,
        )
        Row(
            Modifier
                .clip(RoundedCornerShape(20.dp))
                .background(Mint.copy(alpha = 0.12f))
                .padding(horizontal = 14.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Filled.CreditCard, null, tint = Mint, modifier = Modifier.size(15.dp))
            Spacer(Modifier.width(6.dp))
            Text("$creditos créditos", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Mint)
        }
    }
}

@Composable
private fun OptionRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    subtitle: String,
    onClick: () -> Unit,
    danger: Boolean = false,
) {
    Row(
        Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White)
            .border(1.dp, Hairline, RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icon, null, tint = if (danger) Danger else Mint, modifier = Modifier.size(22.dp))
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(label, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = if (danger) Danger else Ink)
            Text(subtitle, fontSize = 12.sp, color = InkSecondary)
        }
    }
}

@Composable
private fun LanguageDialog(
    languages: List<com.classexpress.app.model.Language>,
    onSelect: (Int) -> Unit,
    onDismiss: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Idioma de la app") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                languages.forEach { lang ->
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { onSelect(lang.id) }
                            .padding(vertical = 12.dp, horizontal = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Filled.Language, null, tint = Mint, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(10.dp))
                        Text(lang.nombre, fontSize = 15.sp, color = Ink)
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) { Text("Cancelar", color = InkSecondary) }
        },
    )
}

@Composable
private fun DeleteAccountDialog(
    deleting: Boolean,
    error: String?,
    onConfirm: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    var password by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = { if (!deleting) onDismiss() },
        title = { Text("Eliminar cuenta", color = Danger) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    "Esta acción eliminará tu cuenta y todos tus datos. Escribe tu contraseña para confirmar.",
                    fontSize = 14.sp,
                    color = InkSecondary,
                )
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    placeholder = { Text("Contraseña") },
                    singleLine = true,
                )
                if (error != null) {
                    Text(error, fontSize = 13.sp, color = Danger)
                }
            }
        },
        confirmButton = {
            TextButton(
                enabled = password.isNotBlank() && !deleting,
                onClick = { onConfirm(password) },
            ) {
                Text(if (deleting) "Eliminando…" else "Eliminar", color = Danger)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !deleting) {
                Text("Cancelar", color = InkSecondary)
            }
        },
    )
}
