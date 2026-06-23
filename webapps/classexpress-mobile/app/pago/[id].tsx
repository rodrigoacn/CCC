import { View, Text, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiPayment } from '@/lib/api';

export default function PagoScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id, precio } = useLocalSearchParams<{ id: string; precio: string }>();
  const { user, refreshUser } = useAuth();
  const qc = useQueryClient();

  const { mutate: pay, isPending, isSuccess, data: result } = useMutation({
    mutationFn: () => apiPayment(Number(id)),
    onSuccess: () => {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      refreshUser();
      qc.invalidateQueries({ queryKey: ['credits'] });
    },
    onError: (e: any) => Alert.alert('Error en el pago', e.message),
  });

  const precioNum = Number(precio ?? 0);
  const botPad = Platform.OS === 'web' ? 34 : insets.bottom;

  if (isSuccess && result) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background, paddingBottom: botPad }]}>
        <View style={[styles.successIcon, { backgroundColor: colors.success + '22' }]}>
          <Feather name="check-circle" size={48} color={colors.success} />
        </View>
        <Text style={[styles.successTitle, { color: colors.foreground }]}>¡Pago exitoso!</Text>
        <Text style={[styles.successSub, { color: colors.subtext }]}>{result.recibo}</Text>
        <Text style={[styles.balanceTxt, { color: colors.primary }]}>
          Saldo restante: {result.creditos_restantes} créditos
        </Text>
        <TouchableOpacity style={[styles.doneBtn, { backgroundColor: colors.primary }]} onPress={() => router.replace('/(tabs)')}>
          <Text style={styles.doneTxt}>Volver al inicio</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={[styles.page, { backgroundColor: colors.background }]}>
      <View style={{ flex: 1, padding: 24 }}>
        <View style={[styles.card, { backgroundColor: colors.card }]}>
          <Feather name="credit-card" size={32} color={colors.primary} style={{ marginBottom: 16 }} />
          <Text style={[styles.cardLabel, { color: colors.subtext }]}>Total a pagar</Text>
          <Text style={[styles.cardAmount, { color: colors.foreground }]}>{precioNum}</Text>
          <Text style={[styles.cardUnit, { color: colors.primary }]}>créditos</Text>
        </View>

        <View style={[styles.infoBox, { backgroundColor: colors.muted }]}>
          <View style={styles.infoRow}>
            <Text style={[styles.infoLabel, { color: colors.subtext }]}>Saldo actual</Text>
            <Text style={[styles.infoVal, { color: colors.foreground }]}>{user?.creditos ?? 0} cr.</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={[styles.infoLabel, { color: colors.subtext }]}>Costo de la clase</Text>
            <Text style={[styles.infoVal, { color: colors.danger }]}>-{precioNum} cr.</Text>
          </View>
          <View style={[styles.infoRow, { borderTopWidth: 1, borderTopColor: colors.border, paddingTop: 12, marginTop: 4 }]}>
            <Text style={[styles.infoLabel, { color: colors.subtext }]}>Saldo después</Text>
            <Text style={[styles.infoVal, { color: colors.success }]}>{(user?.creditos ?? 0) - precioNum} cr.</Text>
          </View>
        </View>

        {(user?.creditos ?? 0) < precioNum && (
          <View style={[styles.warnBox, { backgroundColor: colors.danger + '22' }]}>
            <Feather name="alert-circle" size={16} color={colors.danger} />
            <Text style={[styles.warnTxt, { color: colors.danger }]}>
              Saldo insuficiente. Recarga créditos primero.
            </Text>
          </View>
        )}
      </View>

      <View style={[styles.footer, { paddingBottom: botPad + 16, borderTopColor: colors.border, backgroundColor: colors.surface }]}>
        <TouchableOpacity style={[styles.cancelBtn, { borderColor: colors.border }]} onPress={() => router.back()}>
          <Text style={[styles.cancelTxt, { color: colors.subtext }]}>Cancelar</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.payBtn, { backgroundColor: (user?.creditos ?? 0) < precioNum ? colors.muted : colors.primary }]}
          onPress={() => pay()}
          disabled={isPending || (user?.creditos ?? 0) < precioNum}
        >
          {isPending ? <ActivityIndicator color="#fff" /> : (
            <Text style={[styles.payTxt, { color: (user?.creditos ?? 0) < precioNum ? colors.subtext : '#fff' }]}>
              Confirmar pago
            </Text>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  page:         { flex: 1 },
  center:       { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32, gap: 16 },
  successIcon:  { width: 96, height: 96, borderRadius: 48, justifyContent: 'center', alignItems: 'center', marginBottom: 8 },
  successTitle: { fontFamily: 'Poppins_700Bold', fontSize: 26 },
  successSub:   { fontFamily: 'Poppins_400Regular', fontSize: 15, textAlign: 'center' },
  balanceTxt:   { fontFamily: 'Poppins_600SemiBold', fontSize: 18 },
  doneBtn:      { marginTop: 8, paddingVertical: 14, paddingHorizontal: 40, borderRadius: 16 },
  doneTxt:      { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
  card:         { borderRadius: 20, padding: 28, alignItems: 'center', marginBottom: 20, shadowColor: '#000', shadowOpacity: 0.06, shadowRadius: 12, shadowOffset: { width: 0, height: 4 } },
  cardLabel:    { fontFamily: 'Poppins_400Regular', fontSize: 14, marginBottom: 4 },
  cardAmount:   { fontFamily: 'Poppins_700Bold', fontSize: 64, lineHeight: 72 },
  cardUnit:     { fontFamily: 'Poppins_600SemiBold', fontSize: 18 },
  infoBox:      { borderRadius: 16, padding: 16, gap: 10 },
  infoRow:      { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  infoLabel:    { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  infoVal:      { fontFamily: 'Poppins_700Bold', fontSize: 16 },
  warnBox:      { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 16, borderRadius: 12, padding: 12 },
  warnTxt:      { fontFamily: 'Poppins_400Regular', fontSize: 13, flex: 1 },
  footer:       { flexDirection: 'row', gap: 12, paddingHorizontal: 20, paddingTop: 16, borderTopWidth: 1 },
  cancelBtn:    { flex: 1, paddingVertical: 14, borderRadius: 14, borderWidth: 1, alignItems: 'center' },
  cancelTxt:    { fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  payBtn:       { flex: 2, paddingVertical: 14, borderRadius: 14, alignItems: 'center' },
  payTxt:       { fontFamily: 'Poppins_700Bold', fontSize: 16 },
});
