package com.classexpress.app

import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.classexpress.app.ui.navigation.AppRoot
import com.classexpress.app.ui.theme.ClassExpressTheme
import com.google.android.gms.ads.MobileAds
import com.google.android.gms.ads.initialization.InitializationStatus

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Initialize AdMob
        MobileAds.initialize(this) { initializationStatus: InitializationStatus ->
            Log.d("Ads", "AdMob initialized: $initializationStatus")
        }

        // Set up Compose UI
        setContent {
            ClassExpressTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    AppRoot()
                }
            }
        }
    }
}