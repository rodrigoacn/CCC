import { useState } from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, StyleSheet,
  Platform, ActivityIndicator, Alert, useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiTeacherDashboard, apiClassAction, apiStartRoom, TeacherDashboard } from '@/lib/api';

const DARK = '#1e293b';
const DARK_BORDER = '#334155';
const DARK_CARD = '#0f172a';

function fmtMoney(sym: string, amount: number) {
  return sym + amount.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDur(min: number | null | undefined) {
  if (!min) return '—';
  if (min < 60) return `${min} min`;
  return `${Math.floor(min / 60)}h ${min % 60}m`;
}

export default function DashboardScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const qc = useQueryClient();
  const { t } = useI18n();
  const { width } = useWindowDimensions();
  const compact = width < 720;

  const [busy, setBusy] = useState<number | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['teacher_dashboard'],
    queryFn: apiTeacherDashboard,
    refetchInterval: 15_000,
  });

  const classAction = useMutation({
    mutationFn: ({ id, action }: { id: number; action: 'activate' | 'deactivate' | 'delete' }) =>
      apiClassAction(id, action),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['teacher_dashboard'] });
      qc.invalidateQueries({ queryKey: ['classes'] });
    },
    onSettled: () => setBusy(null),
    onError: (e: any) => setError(e.message || t('general.error')),
  });

  const startRoom = useMutation({
    mutationFn: (clase_id: number) => apiStartRoom(clase_id),
    onSuccess: ({ sala }) => router.push(`/sala/${sala.id}?from=dashboard`),
    onError: (e: any) => setError(e.message || t('general.error')),
  });

  const [error, setError] = useState('');

  const doDelete = (item: TeacherDashboard['clases'][number]) => {
    setBusy(item.id);
    if (Platform.OS === 'web') {
      if (window.confirm(t('dashboard.delete_confirm'))) {
        classAction.mutate({ id: item.id, action: 'delete' });
      } else {
        setBusy(null);
      }
    } else {
      Alert.alert(t('dashboard.title'), t('dashboard.delete_confirm'), [
        { text: t('general.cancel'), style: 'cancel' },
        { text: t('general.delete'), style: 'destructive', onPress: () => classAction.mutate({ id: item.id, action: 'delete' }) },
      ]);
    }
  };

  const doToggle = (item: TeacherDashboard['clases'][number]) => {
    setBusy(item.id);
    setError('');
    classAction.mutate({ id: item.id, action: item.activa ? 'deactivate' : 'activate' });
  };

  const botPad = Platform.OS === 'web' ? 40 : insets.bottom;

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }}>
        <ActivityIndicator color={colors.primary} size="large" />
      </View>
    );
  }

  const d = data as TeacherDashboard;
  const stats = d?.stats ?? { total_clases: 0, clases_activas: 0, total_sesiones: 0, sesiones_pagadas: 0, ganancias_usd: 0 };
  const me = d?.me ?? { nombre: '', rol: '', calificacion: 0, num_resenas: 0 };
  const clases = d?.clases ?? [];
  const sesiones = d?.sesiones ?? [];
  const earnings = d?.earningsByCurrency ?? [];

  const headerBtn = (icon: string, label: string, primary: boolean, onPress: () => void) => (
    <TouchableOpacity
      key={label}
      onPress={onPress}
      style={[styles.headBtn, primary ? { backgroundColor: colors.primary } : { backgroundColor: 'transparent', borderWidth: 1, borderColor: DARK_BORDER }]}
    >
      <Feather name={icon as any} size={15} color={primary ? '#fff' : '#94a3b8'} />
      <Text style={[styles.headBtnTxt, { color: primary ? '#fff' : '#e2e8f0' }]}>{label}</Text>
    </TouchableOpacity>
  );

  const statCard = (title: string, value: string, sub: string, success?: boolean) => (
    <View key={title} style={[styles.statCard, compact && { flexBasis: '47%' }]}>
      <Text style={styles.statLabel}>{title}</Text>
      <Text style={[styles.statValue, success && { color: '#4ade80' }]}>{value}</Text>
      <Text style={styles.statSub}>{sub}</Text>
    </View>
  );

  return (
    <ScrollView style={{ flex: 1, backgroundColor: colors.background }} contentContainerStyle={{ padding: compact ? 14 : 24, paddingBottom: botPad + 24 }}>
      {/* Header */}
      <View style={[styles.headRow, compact && { flexDirection: 'column', alignItems: 'flex-start', gap: 12 }]}>
        <View style={{ flex: 1 }}>
          <Text style={[styles.title, { color: colors.foreground }]}>{t('dashboard.title')}</Text>
          <Text style={styles.welcome}>
            {t('dashboard.welcome', { name: me.nombre })} ·{' '}
            <Text style={styles.roleBadge}>{me.rol}</Text>
            {Number(me.calificacion) > 0 ? (
              <Text style={styles.welcome}> · ⭐ {Number(me.calificacion).toFixed(1)} ({Number(me.num_resenas)} {t('general.reviews')})</Text>
            ) : null}
            {me.pais ? <Text style={styles.welcome}> · 📍 {me.pais}</Text> : null}
          </Text>
        </View>
        <View style={[styles.headBtns, compact && { width: '100%', flexWrap: 'wrap' }]}>
          {headerBtn('plus', t('dashboard.post_class'), true, () => router.push('/profesor/crear'))}
          {headerBtn('zap', t('dashboard.quick_offer_btn'), false, () => router.push('/profesor/crear'))}
          {headerBtn('search', t('dashboard.find_students'), false, () => router.push('/(tabs)/buscar'))}
          {headerBtn('user', t('dashboard.my_profile'), false, () => router.push('/(tabs)/perfil'))}
        </View>
      </View>

      {error ? (
        <View style={styles.errorBox}>
          <Text style={styles.errorTxt}>{error}</Text>
        </View>
      ) : null}

      {/* Stat cards */}
      <View style={[styles.statsGrid, compact && { flexDirection: 'row', flexWrap: 'wrap', gap: 10 }]}>
        {statCard(t('dashboard.active_classes'), String(Number(stats.clases_activas)), t('dashboard.total_posted', { count: Number(stats.total_clases) }))}
        {statCard(t('dashboard.live_students'), String(Number(d?.live ?? 0)), t('dashboard.sessions_total', { count: Number(stats.total_sesiones) }), Number(d?.live ?? 0) > 0)}
        {statCard(t('dashboard.paid_sessions'), String(Number(stats.sesiones_pagadas)), t('dashboard.paid_out_of', { count: Number(stats.total_sesiones) }))}
        {statCard(t('dashboard.total_earnings'), `$${Number(stats.ganancias_usd).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`, t('dashboard.usd_across'), true)}
      </View>

      {/* Earnings by currency */}
      {earnings.length > 0 && (
        <View style={styles.card}>
          <View style={styles.cardHead}>
            <Text style={styles.cardTitle}>{t('dashboard.earnings_by_currency')}</Text>
            <Text style={styles.cardHint}>{t('dashboard.currency_hint')}</Text>
          </View>
          <View style={[styles.earnRow, compact && { flexWrap: 'wrap' }]}>
            {earnings.map((e) => (
              <View key={e.moneda_local + e.simbolo_local} style={[styles.earnBox, compact && { marginBottom: 10 }]}>
                <Text style={styles.earnCur}>{e.moneda_local}</Text>
                <Text style={styles.earnTotal}>{fmtMoney(e.simbolo_local, Number(e.total))}</Text>
                <Text style={styles.earnPay}>
                  {Number(e.num_pagos)} {Number(e.num_pagos) === 1 ? t('dashboard.payments_count', { count: Number(e.num_pagos) }) : t('dashboard.payments_count_plural', { count: Number(e.num_pagos) })}
                </Text>
              </View>
            ))}
          </View>
        </View>
      )}

      {/* My Classes */}
      <View style={styles.card}>
        <View style={styles.cardHead}>
          <Text style={styles.cardTitle}>{t('dashboard.my_classes')}</Text>
          <TouchableOpacity style={styles.cardHeadBtn} onPress={() => router.push('/profesor/crear')}>
            <Text style={{ color: colors.primary, fontWeight: '700', fontSize: 13 }}>{t('dashboard.new_class')}</Text>
          </TouchableOpacity>
        </View>

        {clases.length === 0 ? (
          <View style={styles.emptyBox}>
            <Text style={styles.emptyTxt}>{t('dashboard.no_classes')}</Text>
            <TouchableOpacity style={[styles.firstBtn, { backgroundColor: colors.primary }]} onPress={() => router.push('/profesor/crear')}>
              <Text style={styles.firstBtnTxt}>{t('dashboard.first_class')}</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <View style={styles.tableWrap}>
            {!compact && (
              <View style={styles.thead}>
                <Text style={[styles.th, { flex: 0.6 }]}>{t('dashboard.status')}</Text>
                <Text style={[styles.th, { flex: 1.6 }]}>{t('dashboard.title_header')}</Text>
                <Text style={[styles.th, { flex: 1 }]}>{t('dashboard.subject')}</Text>
                <Text style={[styles.th, { flex: 1 }]}>{t('dashboard.price')}</Text>
                <Text style={[styles.th, { flex: 0.8 }]}>{t('dashboard.students')}</Text>
                <Text style={[styles.th, { flex: 0.8 }]}>{t('dashboard.sessions')}</Text>
                <Text style={[styles.th, { flex: 1 }]}>{t('dashboard.posted')}</Text>
                <Text style={[styles.th, { flex: 1.4, textAlign: 'right' }]}>{t('dashboard.actions')}</Text>
              </View>
            )}
            {clases.map((c) => (
              <View key={c.id} style={[styles.tr, compact && styles.trCompact]}>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 0.6 }]}>
                  {c.activa ? (
                    <View style={[styles.badge, styles.badgeOn]}><Text style={styles.badgeOnTxt}>{t('dashboard.live_badge')}</Text></View>
                  ) : (
                    <View style={[styles.badge, styles.badgeOff]}><Text style={styles.badgeOffTxt}>{t('dashboard.off_badge')}</Text></View>
                  )}
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1.6 }]}>
                  <Text style={styles.tdTitle}>{c.titulo || t('dashboard.quick_offer')}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1 }]}>
                  <Text style={styles.tdSub}>{c.materia ?? '—'}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1 }]}>
                  <Text style={styles.tdSub}>
                    {Number(c.precio_min) > 0 && Number(c.precio_max) > 0
                      ? `${me.simbolo || '$'}${Number(c.precio_min).toFixed(2)} – ${me.simbolo || '$'}${Number(c.precio_max).toFixed(2)}`
                      : `${me.simbolo || '$'}${Number(c.precio).toFixed(2)}`}
                    <Text style={{ color: '#64748b', fontSize: 11 }}> {c.codigo_moneda || ''}</Text>
                  </Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 0.8 }]}>
                  <Text style={styles.tdSub}>{Number(c.alumnos_min) || 1}–{Number(c.alumnos_max) || '∞'}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 0.8 }]}>
                  <Text style={styles.tdSub}>
                    {String(Number(c.num_sesiones) || 0)}
                    {Number(c.num_pagados) > 0 ? <Text style={{ color: '#64748b', fontSize: 11 }}> ({Number(c.num_pagados)} {t('dashboard.paid')})</Text> : null}
                  </Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1 }]}>
                  <Text style={styles.tdSub}>
                    {c.created_at ? new Date(c.created_at).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                  </Text>
                </View>
                <View style={[styles.td, compact && [styles.tdCompact, styles.tdActionsCompact], { flex: compact ? undefined : 1.4, alignItems: 'flex-end' }]}>
                  <View style={styles.trActions}>
                    <TouchableOpacity
                      style={[styles.smBtn, { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.primary }]}
                      disabled={busy === c.id}
                      onPress={() => startRoom.mutate(c.id)}
                    >
                      <Text style={{ color: colors.primary, fontWeight: '600', fontSize: 12 }}>{t('dashboard.join_btn')}</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.smBtn, { backgroundColor: 'transparent', borderWidth: 1, borderColor: c.activa ? '#f59e0b' : '#16a34a' }]}
                      disabled={busy === c.id}
                      onPress={() => doToggle(c)}
                    >
                      <Text style={{ color: c.activa ? '#f59e0b' : '#16a34a', fontWeight: '600', fontSize: 12 }}>
                        {c.activa ? t('dashboard.pause_btn') : t('dashboard.activate_btn')}
                      </Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.smBtn, { backgroundColor: 'transparent', borderWidth: 1, borderColor: '#dc2626' }]}
                      disabled={busy === c.id}
                      onPress={() => doDelete(c)}
                    >
                      <Feather name="trash-2" size={14} color="#dc2626" />
                    </TouchableOpacity>
                  </View>
                </View>
              </View>
            ))}
          </View>
        )}
      </View>

      {/* Recent Sessions */}
      <View style={styles.card}>
        <View style={styles.cardHead}>
          <Text style={styles.cardTitle}>{t('dashboard.recent_sessions')}</Text>
          <Text style={styles.cardHint}>{t('dashboard.last_15')}</Text>
        </View>

        {sesiones.length === 0 ? (
          <View style={styles.emptyBox}>
            <Text style={styles.emptyTxt}>{t('dashboard.no_sessions')}</Text>
          </View>
        ) : (
          <View style={styles.tableWrap}>
            {!compact && (
              <View style={styles.thead}>
                <Text style={[styles.th, { flex: 1.1 }]}>{t('dashboard.student')}</Text>
                <Text style={[styles.th, { flex: 1.2 }]}>{t('dashboard.class')}</Text>
                <Text style={[styles.th, { flex: 1 }]}>{t('dashboard.subject')}</Text>
                <Text style={[styles.th, { flex: 0.8 }]}>{t('dashboard.duration')}</Text>
                <Text style={[styles.th, { flex: 1 }]}>{t('dashboard.amount')}</Text>
                <Text style={[styles.th, { flex: 0.9 }]}>{t('general.status')}</Text>
                <Text style={[styles.th, { flex: 1 }]}>{t('dashboard.date')}</Text>
              </View>
            )}
            {sesiones.map((s) => (
              <View key={s.id} style={[styles.tr, compact && styles.trCompact]}>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1.1 }]}>
                  <Text style={styles.tdTitle}>{s.estudiante}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1.2 }]}>
                  <Text style={styles.tdSub}>{s.clase || t('dashboard.quick_offer')}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1 }]}>
                  <Text style={styles.tdSub}>{s.materia ?? '—'}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 0.8 }]}>
                  <Text style={styles.tdSub}>{fmtDur(Number(s.duracion_min) || null)}</Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1 }]}>
                  <Text style={styles.tdSub}>
                    {Number(s.monto_local) ? (
                      <>
                        <Text style={styles.tdTitle}>{fmtMoney(s.simbolo_local || '$', Number(s.monto_local))}</Text>
                        <Text style={{ color: '#64748b', fontSize: 11 }}> {s.moneda_local || ''}</Text>
                      </>
                    ) : '—'}
                  </Text>
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 0.9 }]}>
                  {Number(s.pagado) ? (
                    <View style={[styles.badge, styles.badgeOn]}><Text style={styles.badgeOnTxt}>{t('dashboard.paid_badge')}</Text></View>
                  ) : s.fin ? (
                    <View style={[styles.badge, styles.badgeWarn]}><Text style={styles.badgeWarnTxt}>{t('dashboard.unpaid_badge')}</Text></View>
                  ) : (
                    <View style={[styles.badge, styles.badgeLive]}><Text style={styles.badgeOnTxt}>{t('dashboard.live_badge_session')}</Text></View>
                  )}
                </View>
                <View style={[styles.td, compact && styles.tdCompact, { flex: compact ? undefined : 1 }]}>
                  <Text style={styles.tdSub}>
                    {s.inicio ? new Date(s.inicio).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + new Date(s.inicio).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) : '—'}
                  </Text>
                </View>
              </View>
            ))}
          </View>
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  headRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 16, marginBottom: 20 },
  title: { fontFamily: 'Poppins_700Bold', fontSize: 26 },
  welcome: { color: '#64748b', fontSize: 13, marginTop: 4 },
  roleBadge: { backgroundColor: '#e2e8f0', color: '#475569', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4, fontSize: 11, textTransform: 'capitalize', overflow: 'hidden', fontFamily: 'Poppins_600SemiBold' },
  headBtns: { flexDirection: 'row', gap: 8 },
  headBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 14, paddingVertical: 10, borderRadius: 10 },
  headBtnTxt: { fontSize: 13, fontFamily: 'Poppins_600SemiBold' },
  errorBox: { backgroundColor: 'rgba(220,38,38,0.12)', borderWidth: 1, borderColor: 'rgba(220,38,38,0.3)', borderRadius: 12, padding: 12, marginBottom: 16 },
  errorTxt: { color: '#dc2626', fontSize: 13 },
  statsGrid: { flexDirection: 'row', gap: 12, marginBottom: 20 },
  statCard: { flex: 1, backgroundColor: DARK_CARD, borderRadius: 14, padding: 16, borderWidth: 1, borderColor: DARK_BORDER },
  statLabel: { color: '#94a3b8', fontSize: 11, textTransform: 'uppercase', letterSpacing: 0.5, fontFamily: 'Poppins_500Medium' },
  statValue: { color: '#ffffff', fontSize: 26, fontFamily: 'Poppins_700Bold', marginTop: 2 },
  statSub: { color: '#64748b', fontSize: 11, marginTop: 2 },
  card: { backgroundColor: DARK, borderRadius: 14, borderWidth: 1, borderColor: DARK_BORDER, marginBottom: 20, overflow: 'hidden' },
  cardHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: 16, borderBottomWidth: 1, borderBottomColor: DARK_BORDER, gap: 8 },
  cardTitle: { color: '#ffffff', fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  cardHint: { color: '#64748b', fontSize: 11 },
  cardHeadBtn: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8, backgroundColor: 'rgba(102,221,189,0.12)' },
  earnRow: { flexDirection: 'row', gap: 12, padding: 16 },
  earnBox: { borderWidth: 1, borderColor: DARK_BORDER, borderRadius: 10, paddingHorizontal: 16, paddingVertical: 12, minWidth: 130, alignItems: 'center' },
  earnCur: { color: '#94a3b8', fontSize: 12 },
  earnTotal: { color: '#ffffff', fontSize: 18, fontFamily: 'Poppins_700Bold', marginTop: 2 },
  earnPay: { color: '#64748b', fontSize: 11, marginTop: 2 },
  emptyBox: { alignItems: 'center', padding: 32 },
  emptyTxt: { color: '#94a3b8', textAlign: 'center', marginBottom: 12, fontSize: 13 },
  firstBtn: { paddingHorizontal: 18, paddingVertical: 10, borderRadius: 10 },
  firstBtnTxt: { color: '#fff', fontWeight: '700', fontSize: 13 },
  tableWrap: {},
  thead: { flexDirection: 'row', paddingHorizontal: 16, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: DARK_BORDER },
  th: { color: '#94a3b8', fontSize: 11, textTransform: 'uppercase', letterSpacing: 0.4, fontFamily: 'Poppins_500Medium' },
  tr: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: 'rgba(51,65,85,0.4)', gap: 10 },
  trCompact: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  td: {},
  tdCompact: { flexBasis: '48%', flexGrow: 1 },
  tdActionsCompact: { flexBasis: '100%', alignItems: 'center', justifyContent: 'flex-end' },
  tdTitle: { color: '#ffffff', fontFamily: 'Poppins_600SemiBold', fontSize: 13 },
  tdSub: { color: '#cbd5e1', fontSize: 12 },
  badge: { alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 },
  badgeOn: { backgroundColor: 'rgba(22,163,74,0.15)' },
  badgeOnTxt: { color: '#4ade80', fontSize: 10, fontWeight: '700' },
  badgeOff: { backgroundColor: 'rgba(100,116,139,0.2)' },
  badgeOffTxt: { color: '#94a3b8', fontSize: 10, fontWeight: '700' },
  badgeWarn: { backgroundColor: 'rgba(245,158,11,0.15)' },
  badgeWarnTxt: { color: '#fbbf24', fontSize: 10, fontWeight: '700' },
  badgeLive: { backgroundColor: 'rgba(102,221,189,0.15)' },
  trActions: { flexDirection: 'row', gap: 6 },
  smBtn: { paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'center' },
});
