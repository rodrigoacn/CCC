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
import { apiSubjects, Subject } from '@/lib/api';

const ICONS: Record<string, any> = {
  calculator: 'hash', 'book-open': 'book-open', feather: 'feather',
  zap: 'zap', activity: 'activity', cpu: 'cpu', map: 'map',
  'pen-tool': 'edit-3', heart: 'heart', globe: 'globe', monitor: 'monitor',
};

function SubjectCard({ item, onPress }: { item: Subject; onPress: () => void }) {
  const colors = useColors();
  return (
    <TouchableOpacity onPress={onPress} style={[styles.card, { backgroundColor: colors.card }]} activeOpacity={0.85}>
      <View style={[styles.iconBox, { backgroundColor: item.color + '22' }]}>
        <Feather name={(ICONS[item.icono] ?? 'book') as any} size={26} color={item.color} />
      </View>
      <Text style={[styles.cardTitle, { color: colors.foreground }]} numberOfLines={2}>{item.nombre}</Text>
      {(item.clases_activas ?? 0) > 0 && (
        <View style={[styles.badge, { backgroundColor: item.color + '22' }]}>
          <Text style={[styles.badgeText, { color: item.color }]}>{item.clases_activas} en vivo</Text>
        </View>
      )}
    </TouchableOpacity>
  );
}

export default function HomeScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { user } = useAuth();

  const { data, isLoading } = useQuery({
    queryKey: ['subjects'],
    queryFn: apiSubjects,
  });
  const subjects = data?.subjects ?? [];

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={[styles.header, { paddingTop: topPad + 12, backgroundColor: colors.background }]}>
        <View>
          <Text style={[styles.greeting, { color: colors.subtext }]}>¡Hola, {user?.nombre?.split(' ')[0]}!</Text>
          <Text style={[styles.headTitle, { color: colors.foreground }]}>¿Qué estudias hoy?</Text>
        </View>
        {user?.rol === 'instructor' && (
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
          data={subjects}
          keyExtractor={i => String(i.id)}
          numColumns={2}
          contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16 }}
          columnWrapperStyle={{ gap: 12 }}
          ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
          renderItem={({ item }) => (
            <SubjectCard
              item={item}
              onPress={() => router.push(`/materia/${item.id}?nombre=${encodeURIComponent(item.nombre)}`)}
            />
          )}
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
  card:      { flex: 1, borderRadius: 18, padding: 16, minHeight: 130, shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 8, shadowOffset: { width: 0, height: 2 } },
  iconBox:   { width: 48, height: 48, borderRadius: 14, justifyContent: 'center', alignItems: 'center', marginBottom: 10 },
  cardTitle: { fontFamily: 'Poppins_600SemiBold', fontSize: 14, flex: 1 },
  badge:     { marginTop: 8, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 20, alignSelf: 'flex-start' },
  badgeText: { fontFamily: 'Poppins_500Medium', fontSize: 11 },
});
