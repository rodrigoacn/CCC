import { useEffect, useState } from 'react';
import { View, ActivityIndicator, StyleSheet, Platform, Text } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { WebView } from 'react-native-webview';
import { useQueryClient } from '@tanstack/react-query';
import { API_URL } from '@/lib/api';
import { useColors } from '@/hooks/useColors';

export default function CheckoutScreen() {
  const { url, type } = useLocalSearchParams<{ url: string; type?: string }>();
  const router = useRouter();
  const qc = useQueryClient();
  const colors = useColors();
  const [error, setError] = useState(false);

  const checkoutUrl = decodeURIComponent(url ?? '');

  useEffect(() => {
    if (!checkoutUrl) {
      setError(true);
    }
  }, [checkoutUrl]);

  const handleNavigationStateChange = (navState: any) => {
    const { url: currentUrl } = navState;

    // Detect success/failure redirect from MercadoPago
    if (currentUrl.includes('mp_success.php') || currentUrl.includes('mp_failure.php')) {
      // Refresh credits data
      qc.invalidateQueries({ queryKey: ['credits'] });

      // Go back to credits screen
      router.back();
    }
  };

  if (error || !checkoutUrl) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Text style={{ color: colors.foreground, fontFamily: 'Poppins_500Medium' }}>
          Error opening checkout
        </Text>
      </View>
    );
  }

  if (Platform.OS === 'web') {
    // On web, redirect directly
    if (typeof window !== 'undefined') {
      window.location.href = checkoutUrl;
    }
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={{ color: colors.subtext, marginTop: 12, fontFamily: 'Poppins_400Regular' }}>
          Redirecting to MercadoPago...
        </Text>
      </View>
    );
  }

  // Native: show WebView
  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <WebView
        source={{ uri: checkoutUrl }}
        style={styles.webview}
        onNavigationStateChange={handleNavigationStateChange}
        startInLoadingState
        renderLoading={() => (
          <View style={[styles.center, StyleSheet.absoluteFill, { backgroundColor: colors.background }]}>
            <ActivityIndicator size="large" color={colors.primary} />
            <Text style={{ color: colors.subtext, marginTop: 12, fontFamily: 'Poppins_400Regular' }}>
              Loading MercadoPago...
            </Text>
          </View>
        )}
        onError={() => setError(true)}
        allowsInlineMediaPlayback
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  webview:   { flex: 1 },
  center:    { flex: 1, justifyContent: 'center', alignItems: 'center' },
});
