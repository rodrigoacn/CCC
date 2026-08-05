import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  Platform, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { apiSubjects, Subject } from '@/lib/api';
import { useRole } from '@/lib/useRole';

const ICONS: Record<string, any> = {
  calculator: 'hash', 'book-open': 'book-open', feather: 'feather',
  zap: 'zap', activity: 'activity', cpu: 'cpu', map: 'map',
  'pen-tool': 'edit-3', heart: 'heart', globe: 'globe', monitor: 'monitor',
};

export default function HomeScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { user } = useAuth();
  const { isTeacher } = useRole();
  const { t } = useI18n();

  const { data, isLoading } = useQuery({
    queryKey: ['subjects'],
    queryFn: apiSubjects,
  });
  const subjects = data?.subjects ?? [];
  const continuarId = user?.ultima_materia;
  const continuar = subjects.find(s => s.id === continuarId);

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const renderCard = ({ item, continuar }: { item: Subject; continuar?: boolean }) => {
    const c = item.color || '#66ddbd';
    const ico = ICONS[item.icono] ?? 'book';
    return (
      <TouchableOpacity
        onPress={() => router.push(`/materia/${item.id}?nombre=${encodeURIComponent(item.nombre)}`)}
        style={[styles.subjectCard, { backgroundColor: c }]}
        activeOpacity={0.85}
      >
        {continuar && (
          <View style={styles.continueTag}>
            <Text style={styles.continueTagText}>{t('home.continue')}</Text>
          </View>
        )}
        <View style={styles.iconBox}>
          <Feather name={continuar ? 'corner-left-up' : ico} size={26} color="#fff" />
        </View>
        <Text style={styles.subjectTitle} numberOfLines={2}>{item.nombre}</Text>
        {(item.clases_activas ?? 0) > 0 && (
          <View style={styles.liveTag}>
            <Text style={styles.liveTagText}>{item.clases_activas} {t('home.live_suffix')}</Text>
          </View>
        )}
      </TouchableOpacity>
    );
  };

  const cards = continuar
    ? [continuar, ...subjects.filter(s => s.id !== continuarId)]
    : subjects;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={[styles.header, { paddingTop: topPad + 12 }]}>
        <View>
          <Text style={[styles.greeting, { color: colors.subtext }]}>
            {t('home.greeting', { name: user?.nombre?.split(' ')[0] ?? '' })}
          </Text>
          <Text style={[styles.headTitle, { color: colors.foreground }]}>
            {t('home.subtitle')}
          </Text>
        </View>
        {isTeacher && (
          <TouchableOpacity
            style={[styles.dashBtn, { backgroundColor: colors.primaryLight }]}
            onPress={() => router.push('/profesor/dashboard')}
          >
            <Feather name="bar-chart-2" size={20} color={colors.primary} />
          </TouchableOpacity>
        )}
      </View>

      {isLoading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator color={colors.primary} size="large" />
        </View>
      ) : (
        <FlatList
          data={cards}
          keyExtractor={i => String(i.id)}
          numColumns={2}
          contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16 }}
          columnWrapperStyle={{ gap: 12 }}
          ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
          renderItem={({ item }) => renderCard({ item, continuar: continuar?.id === item.id })}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  header:    { paddingHorizontal: 20, paddingBottom: 12, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' },
  greeting:  { fontSize: 13, fontFamily: 'Poppins_400Regular' },
  headTitle: { fontSize: 24, fontFamily: 'Poppins_700Bold' },
  dashBtn:   { width: 44, height: 44, borderRadius: 14, justifyContent: 'center', alignItems: 'center' },
  subjectCard: {
    flex: 1, borderRadius: 18, padding: 16, minHeight: 150,
    justifyContent: 'center', alignItems: 'center', position: 'relative',
    boxShadow: '0px 4px 14px rgba(0,0,0,0.12)',
  },
  continueTag: {
    position: 'absolute', top: 12, left: 12,
    paddingHorizontal: 12, paddingVertical: 4, borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.25)',
  },
  continueTagText: { color: '#fff', fontSize: 11, fontWeight: '600', letterSpacing: 0.5 },
  iconBox: {
    width: 64, height: 64, borderRadius: 18,
    backgroundColor: 'rgba(255,255,255,0.22)',
    justifyContent: 'center', alignItems: 'center', marginBottom: 12,
  },
  subjectTitle: { color: '#fff', fontFamily: 'Poppins_600SemiBold', fontSize: 14, lineHeight: 18, textAlign: 'center' },
  liveTag: {
    marginTop: 8, paddingHorizontal: 10, paddingVertical: 3, borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.25)',
  },
  liveTagText: { color: '#fff', fontSize: 11, fontFamily: 'Poppins_500Medium' },
});
