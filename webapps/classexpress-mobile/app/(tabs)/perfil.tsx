import {
  View, Text, TouchableOpacity, StyleSheet, Platform, Alert, ScrollView, Image, TextInput, Modal, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { LANGUAGES } from '@/i18n';
import { useState, useEffect } from 'react';
import * as ImagePicker from 'expo-image-picker';
import { request, apiLanguages, Language } from '@/lib/api';
import { useRole } from '@/lib/useRole';

function MenuItem({ icon, label, onPress, danger }: { icon: any; label: string; onPress: () => void; danger?: boolean }) {
  const colors = useColors();
  return (
    <TouchableOpacity style={[styles.menuItem, { borderBottomColor: colors.border }]} onPress={onPress} activeOpacity={0.7}>
      <View style={[styles.menuIcon, { backgroundColor: danger ? colors.danger + '22' : colors.primaryLight }]}>
        <Feather name={icon} size={18} color={danger ? colors.danger : colors.primary} />
      </View>
      <Text style={[styles.menuLabel, { color: danger ? colors.danger : colors.foreground }]}>{label}</Text>
      {!danger && <Feather name="chevron-right" size={18} color={colors.mutedForeground} />}
    </TouchableOpacity>
  );
}

export default function PerfilScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { user, logout, refreshUser } = useAuth();
  const { lang, setLang, t } = useI18n();
  const { isTeacher, loginRol, setLoginRol } = useRole();
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [showLangModal, setShowLangModal] = useState(false);
  const [avatarLoading, setAvatarLoading] = useState(false);
  const [allLanguages, setAllLanguages] = useState<Language[]>([]);
  const [userLanguageIds, setUserLanguageIds] = useState<number[]>([]);
  const [showLangEditModal, setShowLangEditModal] = useState(false);
  const [showSwitchModal, setShowSwitchModal] = useState(false);
  const [switchPassword, setSwitchPassword] = useState('');
  const [switchLoading, setSwitchLoading] = useState(false);
  const [switchTarget, setSwitchTarget] = useState<'student' | 'teacher'>('student');

  // 30-day lock logic
  const getSwitchLock = (): { locked: boolean; days: number } => {
    if (!user?.last_role_switch) return { locked: false, days: 0 };
    const last = new Date(user.last_role_switch).getTime();
    const daysSince = Math.floor((Date.now() - last) / (86400 * 1000));
    if (daysSince < 30) return { locked: true, days: 30 - daysSince };
    return { locked: false, days: 0 };
  };
  const switchLock = getSwitchLock();
  const canSwitchRole = user?.rol === 'both' || user?.rol === 'instructor' || user?.rol === 'instructor_pendiente';

  useEffect(() => {
    apiLanguages().then(r => setAllLanguages(r.languages)).catch(() => {});
    if (user?.idiomas?.length) {
      apiLanguages().then(r => {
        const ids = r.languages.filter(l => user.idiomas!.includes(l.nombre)).map(l => l.id);
        setUserLanguageIds(ids);
      }).catch(() => {});
    }
  }, []);

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const handleLogout = async () => {
    const ok = Platform.OS === 'web' ? window.confirm(t('profile.logout_confirm')) : await new Promise<boolean>(resolve => {
      Alert.alert(t('profile.logout_title'), t('profile.logout_confirm'), [
        { text: t('credits.cancel'), style: 'cancel', onPress: () => resolve(false) },
        { text: t('profile.logout'), style: 'destructive', onPress: () => resolve(true) },
      ]);
    });
    if (!ok) return;
    await logout();
    router.replace('/(auth)/login');
  };

  const handleDeleteAccount = async () => {
    if (!deletePassword.trim()) {
      Alert.alert('Error', t('profile.delete_password'));
      return;
    }
    
    setDeleteLoading(true);
    try {
      await request<{ ok: boolean; message: string }>('delete_account', { password: deletePassword });
      if (Platform.OS === 'web') {
        window.alert('Tu cuenta ha sido eliminada correctamente.');
        await logout();
        router.replace('/');
      } else {
        Alert.alert('Cuenta eliminada', t('profile.deleted_msg'), [
          { text: 'Ok', onPress: async () => {
            await logout();
    router.replace('/(auth)/login');
          }}
        ]);
      }
    } catch (e: any) {
      if (__DEV__) console.log('delete account error', e);
      Alert.alert('Error', t('profile.delete_error'));
      setDeletePassword('');
    } finally {
      setDeleteLoading(false);
    }
  };

  const handlePickAvatar = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert(t('profile.photo_perm_title'), t('profile.photo_permission'));
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });
    if (result.canceled) return;
    const asset = result.assets[0];
    if (!asset?.uri) return;

    setAvatarLoading(true);
    try {
      let dataUrl: string;
      if (Platform.OS === 'web') {
        dataUrl = asset.uri;
      } else {
        const fs = await import('expo-file-system');
        const base64 = await fs.readAsStringAsync(asset.uri, { encoding: fs.EncodingType.Base64 });
        const mime = asset.mimeType ?? 'image/jpeg';
        dataUrl = `data:${mime};base64,${base64}`;
      }
      const result_data = await request<{ ok: boolean; avatar: string }>('update_avatar', { avatar: dataUrl });
      if (result_data.ok) await refreshUser();
      setAvatarLoading(false);
      Alert.alert('Listo', t('profile.photo_updated'));
    } catch (e: any) {
      setAvatarLoading(false);
      if (__DEV__) console.log('avatar error', e);
      Alert.alert('Error', t('profile.photo_error'));
    }
  };

  const initial = user?.nombre?.[0]?.toUpperCase() ?? '?';

  const handleSwitchRole = async () => {
    if (!switchPassword.trim()) {
      Alert.alert('Error', t('profile.switch_wrong_password'));
      return;
    }
    setSwitchLoading(true);
    try {
      const res = await request<{ ok: boolean; error?: string; days?: number }>('switch_role', {
        password: switchPassword,
        target_role: switchTarget,
      });
      if (res.ok) {
        setSwitchPassword('');
        setShowSwitchModal(false);
        await refreshUser();
        Alert.alert(t('profile.switch_success'), t('profile.switch_success'));
      } else {
        if (res.error === 'locked') {
          Alert.alert(t('profile.switch_locked').replace('{days}', String(res.days ?? 30)));
        } else {
          Alert.alert('Error', t('profile.switch_wrong_password'));
        }
        setSwitchPassword('');
      }
    } catch (e: any) {
      if (__DEV__) console.log('switch role error', e);
      Alert.alert('Error', t('profile.switch_error'));
      setSwitchPassword('');
    } finally {
      setSwitchLoading(false);
    }
  };

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ paddingBottom: insets.bottom + 32 }}
    >
      <View style={[styles.headerWrap, { paddingTop: topPad + 12, backgroundColor: colors.surface }]}>
        <View style={{ position: 'relative', marginBottom: 12 }}>
          {user?.avatar ? (
            <Image source={{ uri: user.avatar }} style={[styles.avatar, { borderWidth: 2, borderColor: colors.primary }]} />
          ) : (
            <View style={[styles.avatar, { backgroundColor: colors.primary }]}> 
              <Text style={styles.avatarLetter}>{initial}</Text> 
            </View>
          )}
          <TouchableOpacity
            style={[styles.cameraBtn, { backgroundColor: colors.primary, borderColor: colors.background }]}
            onPress={handlePickAvatar}
            disabled={avatarLoading}
          >
            {avatarLoading ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <Feather name="camera" size={14} color="#fff" />
            )}
          </TouchableOpacity>
        </View>

        <View style={{ alignItems: 'center', marginBottom: 12 }}>
          <Text style={[styles.name, { color: colors.foreground }]}>{user?.nombre}</Text>
          <Text style={[styles.email, { color: colors.subtext }]}>{user?.email}</Text>
          <Text style={[styles.handle, { color: colors.primary }]}>
            @{user?.username} · {isTeacher ? t('people.teacher') : t('people.student')}
          </Text>
          {isTeacher && user?.calificacion != null && Number(user.calificacion) > 0 && (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 4 }}>
              <Feather name="star" size={15} color="#f59e0b" />
              <Text style={{ color: colors.primary, fontFamily: 'Poppins_700Bold', fontSize: 16 }}>
                {Number(user.calificacion).toFixed(1)}
              </Text>
              <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 12 }}>
                ({user.num_resenas ?? 0} {t('profile.reviews')})
              </Text>
            </View>
          )}
        </View>

        <View style={[styles.statsRow, { backgroundColor: colors.surface, borderColor: colors.border }]}>
          <View style={styles.stat}>
            <Text style={[styles.statNum, { color: colors.primary }]}>{user?.creditos ?? 0}</Text>
            <Text style={[styles.statLabel, { color: colors.subtext }]}>{t('profile.credits')}</Text>
          </View>
          <View style={[styles.statDivider, { backgroundColor: colors.border }]} />
          <View style={styles.stat}>
            <Text style={[styles.statNum, { color: colors.primary }]}>1 USD</Text>
            <Text style={[styles.statLabel, { color: colors.subtext }]}>{t('profile.credit_rate')}</Text>
          </View>
        </View>
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface, marginTop: 12 }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>{t('profile.info')}</Text>
        {user?.biografia ? <Text style={{ color: colors.foreground, fontFamily: 'Poppins_400Regular', fontSize: 13, paddingHorizontal: 16, paddingBottom: 8 }}>{user.biografia}</Text> : null}
        {isTeacher && user?.calificacion != null && (
          <View style={{ flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingBottom: 8, gap: 4 }}>
            <Feather name="star" size={14} color="#f59e0b" />
            <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold', fontSize: 13 }}>
              {Number(user.calificacion).toFixed(1)} · {user.num_resenas ?? 0} {t('profile.reviews')}
            </Text>
          </View>
        )}
        {user?.pais ? (
          <View style={{ flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingBottom: 8, gap: 6 }}>
            <Feather name="map-pin" size={14} color={colors.subtext} />
            <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 13 }}>{user.pais}</Text>
          </View>
        ) : null}
        {user?.idiomas?.length ? (
          <View style={{ flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingBottom: 8, gap: 6 }}>
            <Feather name="globe" size={14} color={colors.subtext} />
            <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 13 }}>{user.idiomas.join(', ')}</Text>
          </View>
        ) : null}
        <MenuItem icon="external-link" label={t('profile.view_public')} onPress={() => router.push(`/perfil_usuario/${user?.username}` as any)} />
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface, marginTop: 12 }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>{t('profile.mode')}</Text>
        <View style={{ flexDirection: 'row', padding: 12, gap: 8 }}>
          {(['student', 'teacher'] as const).map(r => {
            const isActive = loginRol === r;
            const isLocked = switchLock.locked || !canSwitchRole;
            return (
              <TouchableOpacity
                key={r}
                style={{
                  flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6,
                  paddingVertical: 10, borderRadius: 24, borderWidth: 1,
                  borderColor: isActive ? colors.primary : colors.border,
                  backgroundColor: isActive ? colors.primary : 'transparent',
                  opacity: isLocked && !isActive ? 0.5 : 1,
                }}
                onPress={async () => {
                  if (loginRol === r) return;
                  if (isLocked) return;
                  setSwitchTarget(r);
                  setShowSwitchModal(true);
                }}
                disabled={isActive}
              >
                <Feather name={r === 'student' ? 'user' : 'briefcase'} size={16} color={isActive ? '#fff' : colors.subtext} />
                <Text style={{ fontFamily: 'Poppins_600SemiBold', fontSize: 13, color: isActive ? '#fff' : colors.subtext }}>
                  {r === 'student' ? t('profile.student') : t('profile.teacher')}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
        {switchLock.locked && canSwitchRole && (
          <View style={{ paddingHorizontal: 16, paddingBottom: 12 }}>
            <Text style={{ fontSize: 12, color: colors.subtext }}>
              {'\u{1F512}'} {t('profile.switch_locked').replace('{days}', String(switchLock.days))}
            </Text>
          </View>
        )}
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>{t('profile.langs')}</Text>
        <MenuItem icon="globe" label={userLanguageIds.length > 0 ? t('profile.n_langs', { count: userLanguageIds.length }) : t('profile.select_langs')} onPress={() => setShowLangEditModal(true)} />
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>{t('profile.account')}</Text>

        {isTeacher && (
          <MenuItem icon="bar-chart-2" label={t('profile.dashboard')} onPress={() => router.push('/profesor/dashboard')} />
        )}
        {isTeacher && (
          <MenuItem icon="plus-circle" label={t('profile.create_class')} onPress={() => router.push('/profesor/crear')} />
        )}
        <MenuItem icon="credit-card" label={isTeacher ? t('retiro.title') : t('profile.my_credits')} onPress={() => router.push(isTeacher ? '/(tabs)/retiro' : '/(tabs)/creditos')} />
        <MenuItem icon="search" label={t('profile.search')} onPress={() => router.push('/(tabs)/buscar')} />
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface, marginTop: 12 }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>{t('profile.lang_app')}</Text>
        <MenuItem icon="globe" label={LANGUAGES.find(l => l.code === lang)?.label ?? 'Español'} onPress={() => setShowLangModal(true)} />
      </View>

      <View style={[styles.section, { backgroundColor: colors.surface, marginTop: 12 }]}>
        <Text style={[styles.sectionTitle, { color: colors.subtext }]}>{t('profile.session')}</Text>
        <MenuItem icon="log-out" label={t('profile.logout')} onPress={handleLogout} danger />
        <MenuItem icon="trash-2" label={t('profile.delete')} onPress={() => setShowDeleteModal(true)} danger />
      </View>

      <Modal visible={showLangModal} transparent animationType="fade">
        <View style={[styles.modalOverlay, { backgroundColor: 'rgba(0, 0, 0, 0.7)' }]}>
          <View style={[styles.modalContent, { backgroundColor: colors.surface }]}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>{t('profile.select_langs')}</Text>
            {LANGUAGES.map(l => (
              <TouchableOpacity key={l.code}
                style={[styles.langItem, { borderBottomColor: colors.border, backgroundColor: lang === l.code ? colors.primaryLight : 'transparent' }]}
                onPress={() => { setLang(l.code); setShowLangModal(false); }}>
                <Text style={[styles.langLabel, { color: colors.foreground, fontFamily: lang === l.code ? 'Poppins_700Bold' : 'Poppins_500Medium' }]}>
                  {l.label}
                </Text>
                {lang === l.code && <Feather name="check" size={18} color={colors.primary} />}
              </TouchableOpacity>
            ))}
          </View>
        </View>
      </Modal>

      <Modal visible={showDeleteModal} transparent animationType="fade">
        <View style={[styles.modalOverlay, { backgroundColor: 'rgba(0, 0, 0, 0.7)' }]}>
          <View style={[styles.modalContent, { backgroundColor: colors.surface }]}>
            <Text style={[styles.modalTitle, { color: colors.danger }]}>{t('profile.delete_title')}</Text>
            <Text style={[styles.modalMessage, { color: colors.subtext }]}>
              {t('profile.delete_msg')}
            </Text>
            <Text style={[styles.modalLabel, { color: colors.foreground }]}>{t('profile.delete_password')}</Text>
            <TextInput
              style={[styles.modalInput, { backgroundColor: colors.muted, color: colors.foreground, borderColor: colors.border }]}
              placeholder={t('profile.delete_password')}
              placeholderTextColor={colors.mutedForeground}
              secureTextEntry
              autoComplete="off"
              value={deletePassword}
              onChangeText={setDeletePassword}
            />
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.muted }]}
                onPress={() => { setShowDeleteModal(false); setDeletePassword(''); }}
              >
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold' }}>{t('credits.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.danger }]}
                onPress={handleDeleteAccount}
                disabled={deleteLoading}
              >
                <Text style={{ color: '#fff', fontFamily: 'Poppins_600SemiBold' }}>
                {deleteLoading ? t('profile.deleting') : t('profile.delete_btn')}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Switch role modal */}
      <Modal visible={showSwitchModal} transparent animationType="fade">
        <View style={[styles.modalOverlay, { backgroundColor: 'rgba(0, 0, 0, 0.7)' }]}>
          <View style={[styles.modalContent, { backgroundColor: colors.surface }]}>
            <Text style={[styles.modalTitle, { color: colors.primary }]}>{t('profile.switch_confirm_title')}</Text>
            <Text style={[styles.modalMessage, { color: colors.subtext }]}>
              {t('profile.switch_confirm_msg')}
            </Text>
            <Text style={[styles.modalLabel, { color: colors.foreground }]}>{t('profile.switch_confirm_field')}</Text>
            <TextInput
              style={[styles.modalInput, { backgroundColor: colors.muted, color: colors.foreground, borderColor: colors.border }]}
              placeholder={t('profile.switch_confirm_field')}
              placeholderTextColor={colors.mutedForeground}
              secureTextEntry
              autoComplete="off"
              value={switchPassword}
              onChangeText={setSwitchPassword}
            />
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.muted }]}
                onPress={() => { setShowSwitchModal(false); setSwitchPassword(''); }}
              >
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold' }}>{t('credits.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.primary }]}
                onPress={handleSwitchRole}
                disabled={switchLoading}
              >
                <Text style={{ color: '#fff', fontFamily: 'Poppins_600SemiBold' }}>
                  {switchLoading ? '...' : t('profile.switch_confirm_btn')}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Language edit modal */}
      <Modal visible={showLangEditModal} transparent animationType="fade" onRequestClose={() => setShowLangEditModal(false)}>
        <View style={[styles.modalOverlay, { backgroundColor: 'rgba(0, 0, 0, 0.7)' }]}>
          <View style={[styles.modalContent, { backgroundColor: colors.surface, maxHeight: 400 }]}>
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>{t('profile.langs_title')}</Text>
            <ScrollView style={{ flexShrink: 1 }}>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 12 }}>
                {allLanguages.map((lang) => {
                  const sel = userLanguageIds.includes(lang.id);
                  return (
                    <TouchableOpacity
                      key={lang.id}
                      style={{
                        paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20,
                        borderWidth: 1, borderColor: sel ? colors.primary : colors.border,
                        backgroundColor: sel ? colors.primary + '22' : 'transparent',
                      }}
                      onPress={() => setUserLanguageIds(prev =>
                        prev.includes(lang.id) ? prev.filter(id => id !== lang.id) : [...prev, lang.id]
                      )}
                    >
                      <Text style={{ fontFamily: 'Poppins_500Medium', fontSize: 13, color: sel ? colors.primary : colors.foreground }}>{lang.nombre}</Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </ScrollView>
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.muted }]}
                onPress={() => setShowLangEditModal(false)}
              >
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold' }}>{t('credits.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, { backgroundColor: colors.primary }]}
                onPress={async () => {
                  try {
                    await request('update_languages', { idiomas: userLanguageIds });
                    setShowLangEditModal(false);
                  } catch {}
                }}
              >
                <Text style={{ color: '#fff', fontFamily: 'Poppins_600SemiBold' }}>{t('profile.save')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Text style={[styles.version, { color: colors.mutedForeground }]}>{t('profile.version')}</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  headerWrap:  { alignItems: 'center', paddingBottom: 24, paddingHorizontal: 24 },
  avatar:      { width: 80, height: 80, borderRadius: 40, justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
  avatarLetter: { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 32 },
  name:        { fontFamily: 'Poppins_700Bold', fontSize: 22, marginBottom: 2 },
  email:       { fontFamily: 'Poppins_400Regular', fontSize: 14, marginBottom: 10 },
  handle:      { fontFamily: 'Poppins_500Medium', fontSize: 13, marginBottom: 8 },
  rolBadge:    { paddingHorizontal: 16, paddingVertical: 5, borderRadius: 20 },
  rolText:     { fontFamily: 'Poppins_600SemiBold', fontSize: 13 },
  statsRow:    { flexDirection: 'row', marginHorizontal: 20, marginTop: 16, borderRadius: 16, borderWidth: 1, overflow: 'hidden' },
  stat:        { flex: 1, paddingVertical: 16, alignItems: 'center' },
  statNum:     { fontFamily: 'Poppins_700Bold', fontSize: 22 },
  statLabel:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  statDivider: { width: 1 },
  section:     { marginTop: 16, marginHorizontal: 20, borderRadius: 16, overflow: 'hidden' },
  sectionTitle: { fontFamily: 'Poppins_700Bold', fontSize: 11, paddingHorizontal: 16, paddingTop: 12, paddingBottom: 4, letterSpacing: 1 },
  menuItem:    { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, paddingHorizontal: 16, borderBottomWidth: 1 },
  menuIcon:    { width: 36, height: 36, borderRadius: 10, justifyContent: 'center', alignItems: 'center' },
  menuLabel:   { flex: 1, fontFamily: 'Poppins_500Medium', fontSize: 15 },
  version:     { textAlign: 'center', fontFamily: 'Poppins_400Regular', fontSize: 12, marginTop: 32 },
  modalOverlay: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  modalContent: { width: '85%', borderRadius: 16, padding: 20 },
  modalTitle:  { fontFamily: 'Poppins_700Bold', fontSize: 18, marginBottom: 12 },
  modalMessage: { fontFamily: 'Poppins_400Regular', fontSize: 13, marginBottom: 16, lineHeight: 18 },
  modalLabel:  { fontFamily: 'Poppins_500Medium', fontSize: 13, marginBottom: 8 },
  modalInput:  { borderWidth: 1, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, marginBottom: 16, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  modalButtons: { flexDirection: 'row', gap: 10 },
  modalBtn:    { flex: 1, paddingVertical: 12, borderRadius: 10, alignItems: 'center' },
  langItem:    { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, paddingHorizontal: 16, borderBottomWidth: 1, borderRadius: 10, marginBottom: 4 },
  langLabel:   { fontFamily: 'Poppins_500Medium', fontSize: 15 },
  cameraBtn:   { position: 'absolute', bottom: 2, right: 2, width: 28, height: 28, borderRadius: 14, justifyContent: 'center', alignItems: 'center', borderWidth: 2 },
});
