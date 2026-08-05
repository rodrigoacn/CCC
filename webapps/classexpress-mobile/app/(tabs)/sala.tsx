import { View, Text, TouchableOpacity, StyleSheet, ActivityIndicator, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiMyActiveRoom } from '@/lib/api';
import { useRole } from '@/lib/useRole';

export default function SalaScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t } = useI18n();
  const { isTeacher } = useRole();

  const { data, isLoading } = useQuery({
    queryKey: ['my_active_room'],
    queryFn: apiMyActiveRoom,
    refetchInterval: 10000,
  });

  const room = data?.room ?? null;

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  if (isLoading) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background, paddingTop: topPad, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator color={colors.primary} size="large" />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background, paddingTop: topPad }}>
      <View style={{ padding: 20, paddingBottom: 8 }}>
        <Text style={[styles.title, { color: colors.foreground }]}>{t('nav.sala')}</Text>
        <Text style={[styles.subtitle, { color: colors.subtext }]}>
          {room ? 'Tu sala activa' : (isTeacher ? 'No tienes una sala abierta' : 'No estás en ninguna sala')}
        </Text>
      </View>

      {room ? (
        <View style={{ padding: 20 }}>
          <TouchableOpacity
            style={[styles.roomCard, { backgroundColor: colors.card }]}
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
              router.push(`/sala/${room.id}?from=explorar`);
            }}
            activeOpacity={0.85}
          >
            <View style={{ flex: 1 }}>
              <Text style={[styles.roomTitle, { color: colors.foreground }]}>{room.clase}</Text>
              <Text style={[styles.roomSub, { color: colors.subtext }]}>
                {room.precio ? `${room.precio} cr.` : 'Gratis'}
              </Text>
            </View>
            <View style={[styles.liveDot, { backgroundColor: colors.success }]}>
              <Text style={styles.liveText}>EN VIVO</Text>
            </View>
          </TouchableOpacity>
        </View>
      ) : (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 }}>
          <Feather name="video-off" size={48} color={colors.subtext} />
          <Text style={[styles.emptyTxt, { color: colors.subtext }]}>
            {isTeacher
              ? 'Crea una clase y ábrela desde el Dashboard'
              : 'Busca una clase y únete desde la pestaña Buscar'}
          </Text>
          <TouchableOpacity
            style={[styles.goBtn, { backgroundColor: colors.primary }]}
            onPress={() => {
              if (isTeacher) {
                router.push('/profesor/crear');
              } else {
                router.push('/(tabs)/buscar');
              }
            }}
          >
            <Feather name={isTeacher ? 'plus-circle' : 'search'} size={24} color="#fff" />
            <Text style={styles.goBtnTxt}>
              {isTeacher ? 'Crear una clase' : 'Buscar clases'}
            </Text>
          </TouchableOpacity>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  title:    { fontFamily: 'Poppins_700Bold', fontSize: 26, marginBottom: 4 },
  subtitle: { fontFamily: 'Poppins_400Regular', fontSize: 14, marginBottom: 16 },
  emptyTxt: { fontFamily: 'Poppins_500Medium', fontSize: 16, marginTop: 16, marginBottom: 24, textAlign: 'center' },
  goBtn:    { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 18, paddingHorizontal: 36, borderRadius: 16 },
  goBtnTxt: { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 18 },
  roomCard: { flexDirection: 'row', alignItems: 'center', padding: 16, borderRadius: 14, marginBottom: 10 },
  roomTitle: { fontFamily: 'Poppins_600SemiBold', fontSize: 16, marginBottom: 2 },
  roomSub:   { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  liveDot:   { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
  liveText:  { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 10, letterSpacing: 1 },
});
