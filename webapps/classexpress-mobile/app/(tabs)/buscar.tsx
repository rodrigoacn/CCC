import { useState } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity,
  StyleSheet, Platform, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { apiClasses, apiSubjects, Clase } from '@/lib/api';

function ClaseItem({ item, onPress }: { item: Clase; onPress: () => void }) {
  const colors = useColors();
  return (
    <TouchableOpacity style={[styles.item, { backgroundColor: colors.card }]} onPress={onPress} activeOpacity={0.85}>
      <View style={{ flex: 1 }}>
        <Text style={[styles.titulo, { color: colors.foreground }]} numberOfLines={2}>{item.titulo}</Text>
        <Text style={[styles.sub, { color: colors.subtext }]}>{item.profesor} · {item.materia}</Text>
      </View>
      <View style={{ alignItems: 'flex-end', gap: 6 }}>
        <Text style={[styles.precio, { color: colors.primary }]}>{item.precio} cr.</Text>
        {item.sala_activa && (
          <View style={[styles.liveDot, { backgroundColor: colors.success }]}>
            <Text style={styles.liveText}>EN VIVO</Text>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
}

export default function BuscarScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const [search, setSearch] = useState('');
  const [subject, setSubject] = useState(0);
  const [activeOnly, setActiveOnly] = useState(false);

  const { data: subjData } = useQuery({ queryKey: ['subjects'], queryFn: apiSubjects });
  const subjects = subjData?.subjects ?? [];

  const params: Record<string, string> = {};
  if (search)    params.search      = search;
  if (subject)   params.subject_id  = String(subject);
  if (activeOnly) params.active_only = 'true';

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['classes', search, subject, activeOnly],
    queryFn: () => apiClasses(params),
  });
  const classes = data?.classes ?? [];

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={[styles.header, { paddingTop: topPad + 12 }]}>
        <Text style={[styles.headTitle, { color: colors.foreground }]}>Buscar clases</Text>
        <View style={[styles.searchBox, { backgroundColor: colors.muted }]}>
          <Feather name="search" size={18} color={colors.subtext} />
          <TextInput
            style={[styles.searchInput, { color: colors.foreground }]}
            placeholder="Buscar por clase o profesor..."
            placeholderTextColor={colors.mutedForeground}
            value={search}
            onChangeText={setSearch}
          />
          {search.length > 0 && (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Feather name="x" size={18} color={colors.subtext} />
            </TouchableOpacity>
          )}
        </View>

        <View style={styles.filterRow}>
          <TouchableOpacity
            style={[styles.filterChip, activeOnly && { backgroundColor: colors.success }]}
            onPress={() => setActiveOnly(v => !v)}
          >
            <Feather name="radio" size={13} color={activeOnly ? '#fff' : colors.subtext} />
            <Text style={{ color: activeOnly ? '#fff' : colors.subtext, fontFamily: 'Poppins_500Medium', fontSize: 12 }}>
              En vivo
            </Text>
          </TouchableOpacity>

          <FlatList
            horizontal
            data={subjects}
            keyExtractor={i => String(i.id)}
            showsHorizontalScrollIndicator={false}
            renderItem={({ item }) => (
              <TouchableOpacity
                style={[styles.filterChip, subject === item.id && { backgroundColor: colors.primary }]}
                onPress={() => setSubject(prev => prev === item.id ? 0 : item.id)}
              >
                <Text style={{ color: subject === item.id ? '#fff' : colors.subtext, fontFamily: 'Poppins_500Medium', fontSize: 12 }}>
                  {item.nombre}
                </Text>
              </TouchableOpacity>
            )}
          />
        </View>
      </View>

      {isLoading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          data={classes}
          keyExtractor={i => String(i.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16 }}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          onRefresh={refetch}
          refreshing={isLoading}
          ListEmptyComponent={
            <View style={{ alignItems: 'center', paddingTop: 60 }}>
              <Feather name="search" size={40} color={colors.mutedForeground} />
              <Text style={{ color: colors.subtext, marginTop: 12, fontFamily: 'Poppins_400Regular' }}>
                Sin resultados
              </Text>
            </View>
          }
          renderItem={({ item }) => (
            <ClaseItem
              item={item}
              onPress={() => router.push(`/materia/clase?id=${item.id}`)}
            />
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  header:      { paddingHorizontal: 20, paddingBottom: 12 },
  headTitle:   { fontSize: 24, fontFamily: 'Poppins_700Bold', marginBottom: 14 },
  searchBox:   { flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: 14, paddingHorizontal: 14, paddingVertical: 10, marginBottom: 12 },
  searchInput: { flex: 1, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  filterRow:   { flexDirection: 'row', alignItems: 'center', gap: 8 },
  filterChip:  { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, backgroundColor: '#88888833' },
  item:        { borderRadius: 16, padding: 16, flexDirection: 'row', alignItems: 'center', boxShadow: '0px 2px 6px rgba(0,0,0,0.05)' },
  titulo:      { fontFamily: 'Poppins_600SemiBold', fontSize: 14, marginBottom: 4 },
  sub:         { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  precio:      { fontFamily: 'Poppins_700Bold', fontSize: 16 },
  liveDot:     { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 20 },
  liveText:    { color: '#fff', fontSize: 10, fontFamily: 'Poppins_700Bold' },
});
