package com.classexpress.app.ui.navigation

import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AdminPanelSettings
import androidx.compose.material.icons.filled.CreditCard
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.classexpress.app.Container
import com.classexpress.app.data.AuthState
import com.classexpress.app.ui.admin.AdminScreen
import com.classexpress.app.ui.auth.AuthScreen
import com.classexpress.app.ui.auth.ForgotPasswordScreen
import com.classexpress.app.ui.auth.VerifyScreen
import com.classexpress.app.ui.classes.ClasesScreen
import com.classexpress.app.ui.components.BannerAd
import com.classexpress.app.ui.credits.CreditsScreen
import com.classexpress.app.ui.dashboard.TeacherDashboardScreen
import com.classexpress.app.ui.payment.PaymentScreen
import com.classexpress.app.ui.presala.PreSalaScreen
import com.classexpress.app.ui.profile.PerfilScreen
import com.classexpress.app.ui.sala.SalaScreen
import com.classexpress.app.ui.sala.MiSalaScreen
import com.classexpress.app.ui.search.BuscarScreen
import com.classexpress.app.ui.subjects.MateriasScreen
import com.classexpress.app.ui.teacher.CreateClassScreen
import com.classexpress.app.ui.teacher.TeacherRoomScreen
import com.classexpress.app.ui.theme.InkSecondary
import com.classexpress.app.ui.theme.Mint
import com.classexpress.app.ui.theme.PageBackground
import com.classexpress.app.ui.wallet.RetiroScreen

@Composable
fun AppRoot() {
    val auth by Container.session.auth.collectAsStateWithLifecycle()
    when (auth) {
        AuthState.Loading -> SplashView()
        AuthState.LoggedOut -> AuthGraph()
        is AuthState.LoggedIn -> MainGraph()
    }
}

@Composable
private fun SplashView() {
    Box(
        Modifier.fillMaxSize().background(PageBackground),
        contentAlignment = Alignment.Center,
    ) {
        CircularProgressIndicator(color = Mint)
    }
}

@Composable
private fun AuthGraph() {
    val navController = rememberNavController()
    Column(Modifier.fillMaxSize()) {
        NavHost(
            navController = navController,
            startDestination = "login",
            modifier = Modifier.weight(1f),
        ) {
            composable("login") { AuthScreen(navController) }
            composable("forgot") { ForgotPasswordScreen(navController) }
            composable("verify") { VerifyScreen(navController) }
        }
        BannerAd()
    }
}

private data class TabItem(val route: String, val label: String, val icon: ImageVector)

private fun tabsFor(modo: String, rol: String): List<TabItem> {
    val base = if (modo == "teacher") {
        listOf(
            TabItem("panel", "Panel", Icons.Filled.Dashboard),
            TabItem("retiro", "Retirar", Icons.Filled.Payments),
            TabItem("perfil", "Perfil", Icons.Filled.Person),
        )
    } else {
        listOf(
            TabItem("materias", "Materias", Icons.Filled.Home),
            TabItem("buscar", "Buscar", Icons.Filled.Search),
            TabItem("mi_sala", "Sala", Icons.Filled.Videocam),
            TabItem("creditos", "Créditos", Icons.Filled.CreditCard),
            TabItem("perfil", "Perfil", Icons.Filled.Person),
        )
    }
    return if (rol == "admin") base + TabItem("admin", "Admin", Icons.Filled.AdminPanelSettings) else base
}

@Composable
fun MainGraph() {
    val navController = rememberNavController()
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route
    val modo by Container.session.modo.collectAsStateWithLifecycle()
    val user by Container.session.user.collectAsStateWithLifecycle()
    val tabs = remember(modo, user?.rol) { tabsFor(modo, user?.rol ?: "") }
    val showBottomBar = tabs.any { it.route == currentRoute }
    val startDestination = if (modo == "teacher") "panel" else "materias"

    Scaffold(
        containerColor = PageBackground,
        bottomBar = {
            Column {
                if (showBottomBar) {
                    NavigationBar(containerColor = Color.White) {
                        tabs.forEach { tab ->
                            val selected = currentRoute == tab.route
                            NavigationBarItem(
                                selected = selected,
                                onClick = {
                                    navController.navigate(tab.route) {
                                        popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                                        launchSingleTop = true
                                        restoreState = true
                                    }
                                },
                                icon = {
                                    Icon(
                                        tab.icon,
                                        contentDescription = tab.label,
                                        modifier = Modifier,
                                    )
                                },
                                label = { Text(tab.label, fontSize = 11.sp) },
                                colors = NavigationBarItemDefaults.colors(
                                    indicatorColor = Mint.copy(alpha = 0.15f),
                                    selectedIconColor = Mint,
                                    selectedTextColor = Mint,
                                    unselectedIconColor = InkSecondary,
                                    unselectedTextColor = InkSecondary,
                                ),
                            )
                        }
                    }
                }
                BannerAd()
            }
        },
    ) { padding ->
        NavHost(
            navController = navController,
            startDestination = startDestination,
            modifier = Modifier.padding(padding),
        ) {
            composable("materias") {
                MateriasScreen(
                    onOpenSubject = { id, nombre ->
                        navController.navigate("clases/$id?nombre=${Uri.encode(nombre)}")
                    },
                )
            }
            composable("buscar") {
                BuscarScreen(
                    onOpenClass = { id ->
                        navController.navigate("clase/$id?from=buscar")
                    },
                )
            }
            composable("mi_sala") {
                MiSalaScreen(
                    onOpenRoom = { salaId, claseId ->
                        navController.navigate("sala/$claseId?salaId=$salaId&from=mi_sala")
                    },
                    onSearch = { navController.navigate("buscar") },
                )
            }
            composable("creditos") {
                CreditsScreen(
                    onOpenPending = { sesionId ->
                        navController.navigate("pago/$sesionId")
                    },
                )
            }
            composable("perfil") {
                PerfilScreen(
                    onOpenDashboard = { navController.navigate("panel") },
                    onOpenAdmin = { navController.navigate("admin") },
                )
            }

            composable("panel") {
                TeacherDashboardScreen(
                    onCreateClass = { navController.navigate("crear_clase") },
                    onWithdraw = { navController.navigate("retiro") },
                    onOpenRoom = { claseId, salaId ->
                        navController.navigate("sala_profesor/$claseId?salaId=$salaId")
                    },
                    onExit = { navController.popBackStack() },
                )
            }

            composable("crear_clase") {
                CreateClassScreen(
                    onBack = { navController.popBackStack() },
                    onCreated = { navController.popBackStack() },
                )
            }

            composable("retiro") {
                RetiroScreen(onBack = { navController.popBackStack() })
            }

            composable("admin") {
                AdminScreen(onBack = { navController.popBackStack() })
            }

            composable(
                route = "sala_profesor/{claseId}?salaId={salaId}",
                arguments = listOf(
                    navArgument("claseId") { type = NavType.IntType },
                    navArgument("salaId") { type = NavType.IntType; defaultValue = 0 },
                ),
            ) { entry ->
                val claseId = entry.arguments?.getInt("claseId") ?: 0
                val salaId = entry.arguments?.getInt("salaId") ?: 0
                TeacherRoomScreen(
                    claseId = claseId,
                    salaId = salaId,
                    titulo = "",
                    onExit = { navController.popBackStack() },
                )
            }

            composable(
                route = "clases/{materiaId}?nombre={nombre}",
                arguments = listOf(
                    navArgument("materiaId") { type = NavType.IntType },
                    navArgument("nombre") { type = NavType.StringType; defaultValue = "" },
                ),
            ) { entry ->
                val materiaId = entry.arguments?.getInt("materiaId") ?: 0
                val nombre = entry.arguments?.getString("nombre") ?: ""
                ClasesScreen(
                    materiaId = materiaId,
                    nombre = nombre,
                    onBack = { navController.popBackStack() },
                    onOpenClass = { id ->
                        navController.navigate("clase/$id?from=explorar")
                    },
                )
            }

            composable(
                route = "clase/{claseId}?from={from}",
                arguments = listOf(
                    navArgument("claseId") { type = NavType.IntType },
                    navArgument("from") { type = NavType.StringType; defaultValue = "explorar" },
                ),
            ) { entry ->
                val claseId = entry.arguments?.getInt("claseId") ?: 0
                PreSalaScreen(
                    claseId = claseId,
                    onBack = { navController.popBackStack() },
                    onEnter = { id, salaId ->
                        navController.navigate("sala/$id?salaId=$salaId&from=explorar")
                    },
                )
            }

            composable(
                route = "sala/{claseId}?salaId={salaId}&from={from}",
                arguments = listOf(
                    navArgument("claseId") { type = NavType.IntType },
                    navArgument("salaId") { type = NavType.IntType },
                    navArgument("from") { type = NavType.StringType; defaultValue = "explorar" },
                ),
            ) { entry ->
                val claseId = entry.arguments?.getInt("claseId") ?: 0
                val salaId = entry.arguments?.getInt("salaId") ?: 0
                SalaScreen(
                    claseId = claseId,
                    salaId = salaId,
                    onExit = { navController.popBackStack() },
                )
            }

            composable(
                route = "pago/{sesionId}",
                arguments = listOf(navArgument("sesionId") { type = NavType.IntType }),
            ) { entry ->
                val sesionId = entry.arguments?.getInt("sesionId") ?: 0
                PaymentScreen(
                    sesionId = sesionId,
                    onDone = { navController.popBackStack() },
                )
            }
        }
    }
}
