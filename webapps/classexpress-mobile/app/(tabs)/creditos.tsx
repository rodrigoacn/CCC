import { useState } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  Platform, ActivityIndicator, TextInput, Modal,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiCredits, apiTopup, Pago } from '@/lib/api';
import { useRole } from '@/lib/useRole';

const PACKS = [10, 25, 50, 100, 200];

export default function CreditosScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const { t } = useI18n();
  const router = useRouter();
  const { isTeacher } = useRole();
  const [modal, setModal] = useState(false);
  const [custom, setCustom] = useState('');
  const [error, setError] = useState('');

  const { data, isLoading } = useQuery({ queryKey: ['credits'], queryFn: apiCredits });
  const balance = data?.balance ?? 0;
  const tokens = data?.tokens ?? 0;
  const history = data?.history ?? [];

  const openCheckout = (checkoutUrl: string) => {
    if (Platform.OS === 'web') {
      if (typeof window !== 'undefined') window.location.href = checkoutUrl;
    } else {
      router.push({ pathname: '/checkout' as any, params: { url: checkoutUrl, type: 'payment' } });
    }
  };

  const { mutate: topup, isPending } = useMutation({
    mutationFn: (amount: number) => apiTopup(amount),
    onSuccess: (data) => {
      try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success); } catch {}
      setModal(false);
      setError('');
      if (data?.checkout_url) {
        openCheckout(data.checkout_url);
      }
    },
    onError: (e: any) => {
      setError(e.message || t('general.error'));
    },
  });

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const renderItem = ({ item }: { item: Pago }) => (
    <View style={[styles.row, { borderBottomColor: colors.border }]}>
      <View style={[styles.rowIcon, { backgroundColor: item.monto > 0 ? colors.success + '22' : colors.danger + '22' }]}>
        <Feather name={item.monto > 0 ? 'arrow-down-left' : 'arrow-up-right'} size={18}
          color={item.monto > 0 ? colors.success : colors.danger} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.rowDesc, { color: colors.foreground }]} numberOfLines={1}>{item.descripcion}</Text>
        <Text style={[styles.rowDate, { color: colors.subtext }]}>{new Date(item.created_at).toLocaleDateString('es-ES')}</Text>
      </View>
      <Text style={[styles.rowAmount, { color: item.monto > 0 ? colors.success : colors.danger }]}>
        {item.monto > 0 ? '+' : ''}{item.monto} {t('tokens.unit_label')}
      </Text>
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={[styles.headerWrap, { paddingTop: topPad + 12, backgroundColor: colors.primary }]}>
        <Text style={styles.headerTitle}>{t('credits.title')}</Text>
        {isLoading
          ? <ActivityIndicator color="#fff" />
          : <Text style={styles.balance}>{balance}</Text>}
        <Text style={styles.creditLabel}>{t('credits.available')}</Text>
        <View style={styles.btnRow}>
          <TouchableOpacity style={styles.addBtn} onPress={() => { setCustom(''); setError(''); setModal(true); }}>
            <Feather name="plus" size={18} color={colors.primary} />
            <Text style={[styles.addText, { color: colors.primary }]}>{t('credits.recharge')}</Text>
          </TouchableOpacity>
        </View>
      </View>

      {error ? (
        <View style={{ marginHorizontal: 20, marginTop: 16, padding: 10, borderRadius: 12, backgroundColor: `${colors.danger}22` }}>
          <Text style={{ color: colors.danger, fontFamily: 'Poppins_400Regular', fontSize: 13, textAlign: 'center' }}>{error}</Text>
        </View>
      ) : null}

      <View style={[styles.tokenCard, { backgroundColor: colors.card, marginHorizontal: 20, marginTop: 20, padding: 16, borderRadius: 16 }]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <View>
            <Text style={[styles.tokenLabel, { color: colors.subtext }]}>{t('credits.token_balance')}</Text>
            <Text style={[styles.tokenBalance, { color: colors.foreground }]}>{tokens}</Text>
          </View>
          <Feather name="dollar-sign" size={24} color={colors.success} />
        </View>

        {isTeacher && balance > 0 && (
          <TouchableOpacity
            style={[styles.tokenBtn, { backgroundColor: colors.primary, marginTop: 12, justifyContent: 'center' }]}
            onPress={() => router.push('/(tabs)/retiro')}
          >
            <Feather name="credit-card" size={16} color="#fff" />
            <Text style={[styles.addText, { color: '#fff' }]}>{t('retiro.withdraw')}</Text>
          </TouchableOpacity>
        )}
      </View>

      <Text style={[styles.sectionTitle, { color: colors.foreground, paddingHorizontal: 20, paddingTop: 20 }]}>
        {t('credits.history')}
      </Text>

      <FlatList
        data={history}
        keyExtractor={i => String(i.id)}
        renderItem={renderItem}
        contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
        ListEmptyComponent={
          !isLoading ? (
            <View style={{ alignItems: 'center', paddingTop: 40 }}>
              <Feather name="inbox" size={36} color={colors.mutedForeground} />
              <Text style={{ color: colors.subtext, marginTop: 10, fontFamily: 'Poppins_400Regular' }}>
                {t('credits.empty')}
              </Text>
            </View>
          ) : null
        }
      />

      <Modal visible={modal} transparent animationType="slide">
        <View style={styles.modalOverlay}>
          <View style={[styles.modalCard, { backgroundColor: colors.card }]}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>{t('credits.modal_title')}</Text>
            <Text style={[styles.modalSub, { color: colors.subtext }]}>{t('credits.modal_sub')}</Text>

            <View style={styles.packRow}>
              {PACKS.map(p => (
                <TouchableOpacity key={p} style={[styles.pack, { backgroundColor: colors.primaryLight }]}
                  onPress={() => topup(p)} disabled={isPending}>
                  <Text style={[styles.packNum, { color: colors.primary }]}>{p}</Text>
                  <Text style={[styles.packLabel, { color: colors.subtext }]}>cr.</Text>
                </TouchableOpacity>
              ))}
            </View>

            <View style={[styles.customRow, { backgroundColor: colors.muted }]}>
              <TextInput
                style={[styles.customInput, { color: colors.foreground }]}
                placeholder={t('credits.custom')}
                placeholderTextColor={colors.mutedForeground}
                value={custom}
                onChangeText={setCustom}
                keyboardType="numeric"
              />
              <TouchableOpacity
                style={[styles.customBtn, { backgroundColor: colors.primary }]}
                onPress={() => topup(Number(custom))}
                disabled={isPending || !custom}
              >
                {isPending ? <ActivityIndicator color="#fff" size="small" /> : <Feather name="check" size={20} color="#fff" />}
              </TouchableOpacity>
            </View>

            <TouchableOpacity onPress={() => setModal(false)} style={{ alignItems: 'center', marginTop: 16 }}>
              <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular' }}>{t('credits.cancel')}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  headerWrap:  { paddingHorizontal: 24, paddingBottom: 28, borderBottomLeftRadius: 28, borderBottomRightRadius: 28 },
  headerTitle: { color: 'rgba(255,255,255,0.8)', fontFamily: 'Poppins_500Medium', fontSize: 14, marginBottom: 8 },
  balance:     { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 56, lineHeight: 64 },
  creditLabel: { color: 'rgba(255,255,255,0.7)', fontFamily: 'Poppins_400Regular', fontSize: 14, marginBottom: 20 },
  btnRow:      { flexDirection: 'row', gap: 10 },
  addBtn:      { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#fff', paddingVertical: 12, paddingHorizontal: 24, borderRadius: 14 },
  tokenBtn:    { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 12, paddingHorizontal: 24, borderRadius: 14 },
  addText:     { fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  tokenCard:   { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  tokenLabel:  { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  tokenBalance:{ fontFamily: 'Poppins_700Bold', fontSize: 24 },
  tokenSub:    { fontFamily: 'Poppins_400Regular', fontSize: 11 },
  sectionTitle: { fontFamily: 'Poppins_700Bold', fontSize: 18, marginBottom: 4 },
  row:         { flexDirection: 'row', alignItems: 'center', paddingVertical: 14, paddingHorizontal: 20, gap: 12, borderBottomWidth: 1 },
  rowIcon:     { width: 40, height: 40, borderRadius: 12, justifyContent: 'center', alignItems: 'center' },
  rowDesc:     { fontFamily: 'Poppins_500Medium', fontSize: 14 },
  rowDate:     { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  rowAmount:   { fontFamily: 'Poppins_700Bold', fontSize: 16 },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  modalCard:   { borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 28 },
  modalTitle:  { fontFamily: 'Poppins_700Bold', fontSize: 20, marginBottom: 4 },
  modalSub:    { fontFamily: 'Poppins_400Regular', fontSize: 13, marginBottom: 20 },
  packRow:     { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 16 },
  pack:        { width: 68, height: 68, borderRadius: 16, justifyContent: 'center', alignItems: 'center' },
  packNum:     { fontFamily: 'Poppins_700Bold', fontSize: 20 },
  packLabel:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  customRow:   { flexDirection: 'row', alignItems: 'center', borderRadius: 14, overflow: 'hidden' },
  customInput: { flex: 1, paddingHorizontal: 16, paddingVertical: 12, fontFamily: 'Poppins_400Regular', fontSize: 15 },
  customBtn:   { paddingHorizontal: 20, paddingVertical: 12 },
});
