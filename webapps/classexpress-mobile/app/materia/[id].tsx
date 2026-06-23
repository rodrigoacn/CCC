import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, ActivityIndicator, Platform,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { apiClasses, Clase } from '@/lib/api';

function ClaseCard({ item, onPress }: { item: Clase; onPress: () => void }) {
  const colors = useColors();
  return (
    <TouchableOpacity style={[styles.card, { backgroundColor: colors.card }]} onPress={onPress} activeOpacity={0.85}>
      <View style={{ flex: 1, gap: 4 }}>
        <View style={styles.cardTop}>
          <Text style={[styles.titulo, { color: colors.foreground }]} numberOfLines={2}>{item.titulo}</Text>
          {item.sala_activa && (
            <View style={[styles.livePill, { backgroundColor: colors.danger }]}>
              <View style={styles.livePulse} />
              <Text style={styles.liveLabel}>EN VIVO</Text>
            </View>
          )}
        </View>
        <Text style={[styles.profesor, { color: colors.subtext }]}>{item.profesor}</Text>
        {item.descripcion ? (
          <Text style={[styles.desc, { color: colors.mutedForeground }]} numberOfLines={2}>{item.descripcion}</Text>
        ) : null}
        <View style={styles.metaRow}>
          {item.duracion_minutos ? (
            <View style={styles.meta}>
              <Feather name="clock" size={12} color={colors.subtext} />
              <Text style={[styles.metaText, { color: colors.subtext }]}>{item.duracion_minutos} min</Text>
            </View>
          ) : null}
          <View style={styles.meta}>
            <Feather name="star" size={12} color={colors.warning} />
            <Text style={[styles.metaText, { color: colors.subtext }]}>{Number(item.rating ?? 4).toFixed(1)}</Text>
          </View>
        </View>
      </View>
      <View style={styles.priceWrap}>
        <Text style={[styles.price, { color: colors.primary }]}>{item.precio}</Text>
        <Text style={[styles.priceSub, { color: colors.subtext }]}>cr.</Text>
      </View>
    </TouchableOpacity>
  );
}

export default function MateriaScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id, nombre } = useLocalSearchParams<{ id: string; nombre: string }>();

  const { data, isLoading } = useQuery({
    queryKey: ['classes', id],
    queryFn: () => apiClasses({ subject_id: id }),
  });
  const classes = data?.classes ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={{ paddingHorizontal: 20, paddingBottom: 12 }}>
        <Text style={[styles.pageTitle, { color: colors.foreground }]}>{decodeURIComponent(nombre ?? '')}</Text>
        <Text style={[styles.pageSub, { color: colors.subtext }]}>{classes.length} clase{classes.length !== 1 ? 's' : ''} disponible{classes.length !== 1 ? 's' : ''}</Text>
      </View>

      {isLoading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator color={colors.primary} size="large" />
        </View>
      ) : (
        <FlatList
          data={classes}
          keyExtractor={i => String(i.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: (Platform.OS === 'web' ? 34 : insets.bottom) + 16 }}
          ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
          ListEmptyComponent={
            <View style={{ alignItems: 'center', paddingTop: 60 }}>
              <Feather name="book-open" size={40} color={colors.mutedForeground} />
              <Text style={{ color: colors.subtext, marginTop: 12, fontFamily: 'Poppins_400Regular' }}>
                No hay clases disponibles aún
              </Text>
            </View>
          }
          renderItem={({ item }) => (
            <ClaseCard item={item} onPress={() => router.push(`/materia/clase?id=${item.id}`)} />
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  pageTitle:  { fontFamily: 'Poppins_700Bold', fontSize: 26 },
  pageSub:    { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  card:       { borderRadius: 18, padding: 16, flexDirection: 'row', gap: 12, shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 8, shadowOffset: { width: 0, height: 2 } },
  cardTop:    { flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginBottom: 4 },
  titulo:     { flex: 1, fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  profesor:   { fontFamily: 'Poppins_400Regular', fontSize: 13 },
  desc:       { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  metaRow:    { flexDirection: 'row', gap: 12, marginTop: 6 },
  meta:       { flexDirection: 'row', alignItems: 'center', gap: 4 },
  metaText:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  priceWrap:  { alignItems: 'flex-end', justifyContent: 'center' },
  price:      { fontFamily: 'Poppins_700Bold', fontSize: 22 },
  priceSub:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  livePill:   { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 20 },
  livePulse:  { width: 6, height: 6, borderRadius: 3, backgroundColor: '#fff' },
  liveLabel:  { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 10 },
});
