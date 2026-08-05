import { useState } from 'react';
import {
  View, Text, ScrollView, TextInput, TouchableOpacity,
  StyleSheet, Alert, ActivityIndicator, Platform,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiWithdrawTokens, apiWithdrawalHistory, apiCredits, Withdrawal } from '@/lib/api';

const EXCHANGE_RATE = 950;
const COMMISSION_PCT = 15;
const MIN_WITHDRAW = 10;
const BANKS = [
  'BancoEstado', 'Banco de Chile', 'Banco Santander', 'Banco BCI',
  'Banco Scotiabank', 'Banco Itaú', 'Banco Falabella', 'Banco Ripley',
  'Banco Consorcio', 'Transbank', 'MACH', 'Cuenta RUT', 'Other',
];

export default function RetiroScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const { t } = useI18n();

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const { data: creditsData } = useQuery({
    queryKey: ['credits'],
    queryFn: apiCredits,
  });

  const { data: historyData, isLoading: historyLoading } = useQuery({
    queryKey: ['withdrawal_history'],
    queryFn: apiWithdrawalHistory,
  });

  const tokens = creditsData?.balance ?? 0;

  const [amount, setAmount] = useState(String(MIN_WITHDRAW));
  const [bankName, setBankName] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [accountType, setAccountType] = useState('corriente');
  const [bankModal, setBankModal] = useState(false);
  const [metodoRetiro, setMetodoRetiro] = useState<'banco' | 'paypal'>('banco');
  const [paypalEmail, setPaypalEmail] = useState('');
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  const numAmount = parseInt(amount) || 0;
  const commission = Math.round(numAmount * COMMISSION_PCT / 100 * 100) / 100;
  const netUsd = Math.round((numAmount - commission) * 100) / 100;
  const netClp = Math.round(netUsd * EXCHANGE_RATE);

  const withdrawMutation = useMutation({
    mutationFn: () => apiWithdrawTokens({
      cantidad: numAmount,
      cuenta_bancaria: metodoRetiro === 'banco' ? accountNumber : '',
      nombre_banco: metodoRetiro === 'banco' ? bankName : 'PayPal',
      tipo_cuenta: metodoRetiro === 'banco' ? accountType : 'paypal',
      paypal_email: paypalEmail,
      metodo_retiro: metodoRetiro,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['withdrawal_history'] });
      qc.invalidateQueries({ queryKey: ['credits'] });
      setSuccessMsg(t('retiro.success_msg'));
      setErrorMsg('');
    },
    onError: (err: any) => {
      setErrorMsg(err?.message || t('retiro.error'));
    },
  });

  const handleSubmit = () => {
    if (numAmount < MIN_WITHDRAW) { setErrorMsg(t('retiro.min_error', { min: String(MIN_WITHDRAW) })); return; }
    if (numAmount > tokens) { setErrorMsg(t('retiro.insufficient')); return; }
    if (metodoRetiro === 'paypal' && !paypalEmail.trim()) { setErrorMsg(t('retiro.paypal_required')); return; }
    if (metodoRetiro === 'banco' && !bankName) { setErrorMsg(t('retiro.select_bank')); return; }
    if (metodoRetiro === 'banco' && !accountNumber.trim()) { setErrorMsg(t('retiro.account_required')); return; }
    setErrorMsg('');
    if (Platform.OS === 'web') {
      if (window.confirm(t('retiro.confirm_msg', { amount: String(numAmount) }))) {
        withdrawMutation.mutate();
      }
    } else {
      Alert.alert(t('retiro.confirm'), t('retiro.confirm_msg', { amount: String(numAmount) }), [
        { text: t('common.cancel'), style: 'cancel' },
        { text: t('retiro.confirm_button'), onPress: () => withdrawMutation.mutate() },
      ]);
    }
  };

  const withdrawals = historyData?.withdrawals ?? [];

  return (
    <ScrollView style={[styles.container, { backgroundColor: colors.background }]} contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}>
      <View style={[styles.header, { paddingTop: topPad + 12 }]}>
        <Text style={[styles.title, { color: colors.foreground }]}>{t('retiro.title')}</Text>
      </View>

      <View style={[styles.balanceCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <View>
          <Text style={[styles.balanceNum, { color: colors.primary }]}>{tokens}</Text>
          <Text style={[styles.balanceLabel, { color: colors.subtext }]}>{t('retiro.available_tokens')}</Text>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Text style={[styles.balanceClp, { color: colors.foreground }]}>≈ ${Math.round(tokens * EXCHANGE_RATE).toLocaleString()} CLP</Text>
          <Text style={[styles.balanceLabel, { color: colors.subtext }]}>1 CoinsCE = {EXCHANGE_RATE.toLocaleString()} CLP</Text>
        </View>
      </View>

      {tokens >= MIN_WITHDRAW ? (
        <View style={styles.formSection}>
          <Text style={[styles.sectionTitle, { color: colors.foreground }]}>{t('retiro.request')}</Text>

          <Text style={[styles.label, { color: colors.subtext }]}>{t('retiro.amount')}</Text>
          <TextInput
            style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.foreground }]}
            keyboardType="numeric"
            value={amount}
            onChangeText={setAmount}
            placeholder={t('retiro.amount_placeholder')}
            placeholderTextColor={colors.subtext}
          />

          <Text style={[styles.label, { color: colors.subtext }]}>{t('retiro.method')}</Text>
          <View style={styles.typeRow}>
            <TouchableOpacity
              style={[styles.typeChip, metodoRetiro === 'banco' && { backgroundColor: colors.primary }]}
              onPress={() => setMetodoRetiro('banco')}
            >
              <Text style={[styles.typeChipText, metodoRetiro === 'banco' && { color: '#fff' }, { color: colors.foreground }]}>
                {t('retiro.bank_transfer')}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.typeChip, metodoRetiro === 'paypal' && { backgroundColor: colors.primary }]}
              onPress={() => setMetodoRetiro('paypal')}
            >
              <Text style={[styles.typeChipText, metodoRetiro === 'paypal' && { color: '#fff' }, { color: colors.foreground }]}>
                PayPal
              </Text>
            </TouchableOpacity>
          </View>

          {metodoRetiro === 'banco' ? (
            <>
              <Text style={[styles.label, { color: colors.subtext }]}>{t('retiro.bank')}</Text>
              <TouchableOpacity
                style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border }]}
                onPress={() => setBankModal(true)}
              >
                <Text style={{ color: bankName ? colors.foreground : colors.subtext, fontSize: 15 }}>
                  {bankName || t('retiro.select_bank')}
                </Text>
              </TouchableOpacity>

              <Text style={[styles.label, { color: colors.subtext }]}>{t('retiro.account')}</Text>
              <TextInput
                style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.foreground }]}
                value={accountNumber}
                onChangeText={setAccountNumber}
                placeholder="12345678"
                placeholderTextColor={colors.subtext}
              />

              <Text style={[styles.label, { color: colors.subtext }]}>{t('retiro.account_type')}</Text>
              <View style={styles.typeRow}>
                {(['corriente', 'ahorro', 'rut', 'cvu'] as const).map((type) => (
                  <TouchableOpacity
                    key={type}
                    style={[styles.typeChip, accountType === type && { backgroundColor: colors.primary }]}
                    onPress={() => setAccountType(type)}
                  >
                    <Text style={[styles.typeChipText, accountType === type && { color: '#fff' }, { color: colors.foreground }]}>
                      {type.toUpperCase()}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </>
          ) : (
            <>
              <Text style={[styles.label, { color: colors.subtext }]}>{t('retiro.paypal_email')}</Text>
              <TextInput
                style={[styles.input, { backgroundColor: colors.card, borderColor: colors.border, color: colors.foreground }]}
                keyboardType="email-address"
                autoCapitalize="none"
                value={paypalEmail}
                onChangeText={setPaypalEmail}
                placeholder="your@email.com"
                placeholderTextColor={colors.subtext}
              />
            </>
          )}

          <View style={[styles.calcBox, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.calcRow}>
              <Text style={{ color: colors.subtext }}>{t('retiro.gross')}</Text>
              <Text style={{ color: colors.foreground }}>{numAmount} CoinsCE</Text>
            </View>
            <View style={styles.calcRow}>
              <Text style={{ color: colors.subtext }}>{t('retiro.fee')} ({COMMISSION_PCT}%)</Text>
              <Text style={{ color: '#f85149' }}>-{commission} CoinsCE</Text>
            </View>
            <View style={styles.calcRow}>
              <Text style={{ color: colors.subtext }}>{t('retiro.rate')}</Text>
              <Text style={{ color: colors.foreground }}>1 CoinsCE = {EXCHANGE_RATE.toLocaleString()} CLP</Text>
            </View>
            <View style={[styles.calcRow, styles.calcTotal]}>
              <Text style={{ color: colors.foreground, fontWeight: '700' }}>{t('retiro.net')}</Text>
              <Text style={{ color: '#3fb950', fontWeight: '700' }}>${netClp.toLocaleString()} CLP</Text>
            </View>
          </View>

          <TouchableOpacity
            style={[styles.submitBtn, { backgroundColor: colors.primary }]}
            onPress={handleSubmit}
            disabled={withdrawMutation.isPending}
          >
            {withdrawMutation.isPending ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitBtnText}>{t('retiro.submit')}</Text>
            )}
          </TouchableOpacity>
          {errorMsg ? (
            <View style={[styles.alertBox, { marginTop: 16, backgroundColor: 'rgba(248,81,73,0.12)', borderColor: 'rgba(248,81,73,0.25)' }]}>
              <Text style={{ color: '#f85149' }}>{errorMsg}</Text>
            </View>
          ) : null}
          {successMsg ? (
            <View style={[styles.alertBox, { marginTop: 16, backgroundColor: 'rgba(63,185,80,0.12)', borderColor: 'rgba(63,185,80,0.25)' }]}>
              <Text style={{ color: '#3fb950' }}>{successMsg}</Text>
            </View>
          ) : null}
        </View>
      ) : (
        <View style={[styles.alertBox, { backgroundColor: 'rgba(248,81,73,0.12)', borderColor: 'rgba(248,81,73,0.25)' }]}>
          <Text style={{ color: '#f85149' }}>{t('retiro.min_error', { min: String(MIN_WITHDRAW) })}</Text>
        </View>
      )}

      <Text style={[styles.sectionTitle, { color: colors.foreground, marginTop: 32 }]}>{t('retiro.history')}</Text>
      {historyLoading ? (
        <ActivityIndicator color={colors.primary} style={{ marginTop: 20 }} />
      ) : withdrawals.length === 0 ? (
        <Text style={{ color: colors.subtext, textAlign: 'center', paddingVertical: 24 }}>{t('retiro.no_history')}</Text>
      ) : (
        withdrawals.map((w) => (
          <View key={w.retiroId} style={[styles.historyCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.historyRow}>
              <Text style={{ color: colors.foreground, fontWeight: '700' }}>{w.cantidad} CoinsCE</Text>
              <StatusBadge status={w.estado} />
            </View>
            <Text style={{ color: colors.subtext, fontSize: 12, marginTop: 4 }}>{w.nombre_banco} · ${w.monto_clp.toLocaleString()} CLP</Text>
            <Text style={{ color: colors.subtext, fontSize: 11, marginTop: 2 }}>{new Date(w.created_at).toLocaleDateString()}</Text>
          </View>
        ))
      )}

      {bankModal && (
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: colors.card }]}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>{t('retiro.select_bank')}</Text>
            <ScrollView style={{ maxHeight: 400 }}>
              {BANKS.map((b) => (
                <TouchableOpacity
                  key={b}
                  style={[styles.modalItem, bankName === b && { backgroundColor: `${colors.primary}22` }]}
                  onPress={() => { setBankName(b); setBankModal(false); }}
                >
                  <Text style={{ color: bankName === b ? colors.primary : colors.foreground, fontSize: 15 }}>{b}</Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
            <TouchableOpacity style={styles.modalClose} onPress={() => setBankModal(false)}>
              <Text style={{ color: colors.primary, fontWeight: '700' }}>{t('common.cancel')}</Text>
            </TouchableOpacity>
          </View>
        </View>
      )}
    </ScrollView>
  );
}

function StatusBadge({ status }: { status: string }) {
  const bg = status === 'pendiente' ? 'rgba(240,136,62,0.15)' : status === 'completado' || status === 'aprobado' ? 'rgba(63,185,80,0.15)' : status === 'procesando' ? 'rgba(88,166,255,0.15)' : 'rgba(248,81,73,0.15)';
  const color = status === 'pendiente' ? '#f0883e' : status === 'completado' || status === 'aprobado' ? '#3fb950' : status === 'procesando' ? '#58a6ff' : '#f85149';
  const label = status === 'pendiente' ? 'Pending' : status === 'completado' || status === 'aprobado' ? 'Completed' : status === 'procesando' ? 'Processing' : 'Rejected';
  return (
    <View style={{ backgroundColor: bg, paddingHorizontal: 10, paddingVertical: 3, borderRadius: 12 }}>
      <Text style={{ color, fontSize: 11, fontWeight: '700' }}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingBottom: 16 },
  title: { fontSize: 22, fontFamily: 'Poppins_700Bold' },
  balanceCard: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginHorizontal: 16, padding: 20, borderRadius: 16, borderWidth: 1 },
  balanceNum: { fontSize: 32, fontFamily: 'Poppins_700Bold' },
  balanceClp: { fontSize: 16, fontFamily: 'Poppins_600SemiBold' },
  balanceLabel: { fontSize: 12, marginTop: 2 },
  formSection: { marginTop: 24, paddingHorizontal: 16 },
  sectionTitle: { fontSize: 18, fontFamily: 'Poppins_700Bold', marginBottom: 12, paddingHorizontal: 16 },
  label: { fontSize: 13, fontWeight: '600', marginBottom: 4, marginTop: 12 },
  input: { borderWidth: 1, borderRadius: 12, padding: 12, fontSize: 15 },
  typeRow: { flexDirection: 'row', gap: 8, marginTop: 4 },
  typeChip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
  typeChipText: { fontSize: 12, fontWeight: '700' },
  calcBox: { borderRadius: 12, borderWidth: 1, padding: 16, marginTop: 16 },
  calcRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  calcTotal: { borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.1)', marginTop: 8, paddingTop: 8 },
  submitBtn: { borderRadius: 14, padding: 16, alignItems: 'center', marginTop: 20 },
  submitBtnText: { color: '#fff', fontSize: 16, fontWeight: '700', fontFamily: 'Poppins_600SemiBold' },
  alertBox: { marginHorizontal: 16, marginTop: 16, padding: 16, borderRadius: 12, borderWidth: 1 },
  historyCard: { marginHorizontal: 16, marginBottom: 8, padding: 14, borderRadius: 12, borderWidth: 1 },
  historyRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  modalOverlay: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'center', alignItems: 'center', zIndex: 999 },
  modalContent: { borderRadius: 20, padding: 24, width: '85%', maxHeight: '70%' },
  modalTitle: { fontSize: 18, fontFamily: 'Poppins_700Bold', marginBottom: 16 },
  modalItem: { paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.06)' },
  modalClose: { alignItems: 'center', paddingVertical: 16, marginTop: 8 },
});
