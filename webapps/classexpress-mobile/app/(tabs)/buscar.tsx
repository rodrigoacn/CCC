import { useState, useEffect, useRef, useCallback } from 'react';
import {
  View, Text, FlatList, TextInput, TouchableOpacity,
  StyleSheet, Platform, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery, useInfiniteQuery } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiClasses, apiSubjects, Clase } from '@/lib/api';

const SORT_OPTIONS = [
  { key: 'relevance', labelKey: 'search.sort_relevance' },
  { key: 'popular', labelKey: 'search.sort_popular' },
  { key: 'rating', labelKey: 'search.sort_rating' },
  { key: 'price_asc', labelKey: 'search.sort_price_low' },
  { key: 'price_desc', labelKey: 'search.sort_price_high' },
  { key: 'newest', labelKey: 'search.sort_newest' },
];

function StarRating({ rating }: { rating: number }) {
  const full = Math.floor(rating);
  const half = rating - full >= 0.5;
  return (
    <View style={{ flexDirection: 'row', gap: 1 }}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Feather key={i} name="star" size={11} color={i < full ? '#F59E0B' : i === full && half ? '#F59E0B' : '#CBD5E1'} style={i === full && half ? { opacity: 0.6 } : undefined} />
      ))}
    </View>
  );
}

function ClaseCard({ item, onPress }: { item: Clase; onPress: () => void }) {
  const colors = useColors();
  const { t } = useI18n();
  const live = !!item.sala_activa;
  const mins = item.duracion_minutos || 0;
  const capacity = item.alumnos_max || 0;
  const enrolled = item.alumnos_activos || 0;
  const rating = item.rating || 0;
  const amigo = item.es_amigo === 1;
  return (
    <TouchableOpacity style={[styles.card, { backgroundColor: colors.card, borderColor: live ? colors.success : colors.border }]} onPress={onPress} activeOpacity={0.85}>
      {live && (
        <View style={[styles.liveBadge, { backgroundColor: colors.success }]}>
          <Feather name="radio" size={10} color="#fff" />
          <Text style={styles.liveBadgeText}>{t('search.live')}</Text>
        </View>
      )}
      <Text style={[styles.cardTitle, { color: colors.foreground }]} numberOfLines={2}>{item.titulo}</Text>
      <View style={styles.cardProfRow}>
        <Text style={[styles.cardProf, { color: colors.subtext }]} numberOfLines={1}>{item.profesor}</Text>
        {amigo && (
          <View style={[styles.friendChip, { backgroundColor: `${colors.primary}22` }]}>
            <Feather name="user-check" size={11} color={colors.primary} />
            <Text style={[styles.friendChipText, { color: colors.primary }]}>{t('search.amigo')}</Text>
          </View>
        )}
      </View>
      <View style={styles.cardMeta}>
        <View style={[styles.metaChip, { backgroundColor: colors.muted }]}>
          <Feather name="book-open" size={11} color={colors.subtext} />
          <Text style={[styles.metaText, { color: colors.subtext }]}>{item.materia}</Text>
        </View>
        {mins > 0 && (
          <View style={[styles.metaChip, { backgroundColor: colors.muted }]}>
            <Feather name="clock" size={11} color={colors.subtext} />
            <Text style={[styles.metaText, { color: colors.subtext }]}>{mins}min</Text>
          </View>
        )}
        {capacity > 0 && (
          <View style={[styles.metaChip, { backgroundColor: colors.muted }]}>
            <Feather name="users" size={11} color={colors.subtext} />
            <Text style={[styles.metaText, { color: colors.subtext }]}>{enrolled}/{capacity}</Text>
          </View>
        )}
      </View>
      <View style={styles.cardBottom}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          {rating > 0 && (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
              <StarRating rating={rating} />
              <Text style={[styles.ratingNum, { color: colors.subtext }]}>{rating.toFixed(1)}</Text>
            </View>
          )}
        </View>
        <Text style={[styles.cardPrice, { color: colors.primary }]}>{item.precio} cr.</Text>
      </View>
    </TouchableOpacity>
  );
}

export default function BuscarScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t } = useI18n();
  const flatListRef = useRef<FlatList>(null);

  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [subject, setSubject] = useState(0);
  const [activeOnly, setActiveOnly] = useState(false);
  const [sort, setSort] = useState('relevance');

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const { data: subjData } = useQuery({ queryKey: ['subjects'], queryFn: apiSubjects });
  const subjects = subjData?.subjects ?? [];

  const {
    data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading, refetch,
  } = useInfiniteQuery({
    queryKey: ['classes', debouncedSearch, subject, activeOnly, sort],
    queryFn: ({ pageParam = 1 }) => {
      const params: Record<string, string> = { page: String(pageParam), limit: '20', sort };
      if (debouncedSearch) params.search = debouncedSearch;
      if (subject) params.subject_id = String(subject);
      if (activeOnly) params.active_only = 'true';
      return apiClasses(params);
    },
    getNextPageParam: (lastPage) => {
      if (lastPage && lastPage.page < lastPage.pages) return lastPage.page + 1;
      return undefined;
    },
    initialPageParam: 1,
  });

  const classes = data?.pages?.flatMap(p => p.classes ?? []) ?? [];
  const total = data?.pages?.[0]?.total ?? 0;
  const friendClasses = classes.filter(c => c.es_amigo === 1);
  const moreClasses = classes.filter(c => c.es_amigo !== 1);

  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const renderFooter = () => {
    if (!hasNextPage || isFetchingNextPage) return null;
    return (
      <TouchableOpacity style={[styles.loadMore, { borderColor: colors.border }]} onPress={() => fetchNextPage()}>
        <Text style={{ color: colors.primary, fontFamily: 'Poppins_600SemiBold', fontSize: 13 }}>{t('search.load_more')}</Text>
      </TouchableOpacity>
    );
  };

  const renderCard = useCallback(({ item }: { item: Clase }) => (
    <ClaseCard item={item} onPress={() => router.push(`/materia/clase?id=${item.id}`)} />
  ), [router]);

  const renderSection = (label: string, list: Clase[]) => {
    if (list.length === 0) return null;
    return (
      <View style={{ marginBottom: 18 }}>
        <Text style={[styles.sectionTitle, { color: colors.foreground }]}>{label}</Text>
        <View style={{ marginTop: 8 }}>
          {list.map(item => (
            <View key={item.id} style={{ marginBottom: 10 }}>
              {renderCard({ item })}
            </View>
          ))}
        </View>
      </View>
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={[styles.header, { paddingTop: topPad + 12 }]}>
        <Text style={[styles.headTitle, { color: colors.foreground }]}>{t('search.title')}</Text>
        <View style={[styles.searchBox, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <Feather name="search" size={18} color={colors.subtext} />
          <TextInput
            style={[styles.searchInput, { color: colors.foreground }]}
            placeholder={t('search.placeholder')}
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
            style={[styles.filterChip, activeOnly && { backgroundColor: colors.success, borderColor: colors.success }]}
            onPress={() => setActiveOnly(v => !v)}
          >
            <Feather name="radio" size={13} color={activeOnly ? '#fff' : colors.subtext} />
            <Text style={{ color: activeOnly ? '#fff' : colors.subtext, fontFamily: 'Poppins_500Medium', fontSize: 12 }}>
              {t('search.live')}
            </Text>
          </TouchableOpacity>

          <FlatList
            horizontal
            data={subjects}
            keyExtractor={i => String(i.id)}
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 8 }}
            renderItem={({ item }) => {
              const active = subject === item.id;
              const color = item.color || colors.primary;
              return (
                <TouchableOpacity
                  style={[
                    styles.subjectChip,
                    { borderColor: active ? color : colors.border },
                    active && { backgroundColor: `${color}18` },
                  ]}
                  onPress={() => setSubject(prev => prev === item.id ? 0 : item.id)}
                >
                  <Text style={{ color: active ? color : colors.foreground, fontFamily: active ? 'Poppins_600SemiBold' : 'Poppins_500Medium', fontSize: 12 }}>
                    {item.nombre}
                  </Text>
                </TouchableOpacity>
              );
            }}
          />
        </View>

        <View style={styles.sortBar}>
          <Feather name="shuffle" size={12} color={colors.subtext} />
          <Text style={[styles.sortLabel, { color: colors.subtext }]}>{t('search.sort')}:</Text>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
            {SORT_OPTIONS.map(opt => {
              const active = sort === opt.key;
              return (
                <TouchableOpacity
                  key={opt.key}
                  style={[styles.sortPill, active && { backgroundColor: colors.primary }]}
                  onPress={() => { setSort(opt.key); flatListRef.current?.scrollToOffset({ offset: 0, animated: true }); }}
                >
                  <Text style={{ color: active ? '#fff' : colors.subtext, fontFamily: active ? 'Poppins_600SemiBold' : 'Poppins_400Regular', fontSize: 11 }}>
                    {t(opt.labelKey)}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </View>
        </View>

        {!isLoading && total > 0 && (
          <Text style={[styles.resultCount, { color: colors.subtext }]}>{total} {t('search.results')}</Text>
        )}
      </View>

      {isLoading ? (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList
          ref={flatListRef}
          data={[0]}
          keyExtractor={() => 'sections'}
          renderItem={() => null}
          contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16 }}
          ListHeaderComponent={
            <View>
              {renderSection(t('search.friend_classes'), friendClasses)}
              {renderSection(t('search.more_classes'), moreClasses)}
              {total === 0 && (
                <View style={{ alignItems: 'center', paddingTop: 40 }}>
                  <Feather name="search" size={40} color={colors.mutedForeground} />
                  <Text style={{ color: colors.subtext, marginTop: 12, fontFamily: 'Poppins_400Regular', textAlign: 'center' }}>
                    {t('search.empty')}
                  </Text>
                </View>
              )}
            </View>
          }
          onRefresh={refetch}
          refreshing={isLoading}
          onEndReached={() => { if (hasNextPage && !isFetchingNextPage) fetchNextPage(); }}
          onEndReachedThreshold={0.3}
          ListFooterComponent={renderFooter}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  header:      { paddingHorizontal: 20, paddingBottom: 8 },
  headTitle:   { fontSize: 24, fontFamily: 'Poppins_700Bold', marginBottom: 14 },
  searchBox:   { flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: 14, borderWidth: 1, paddingHorizontal: 14, paddingVertical: 10, marginBottom: 12 },
  searchInput: { flex: 1, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  filterRow:   { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8 },
  filterChip:  { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, borderWidth: 1, borderColor: 'transparent', backgroundColor: '#88888833' },
  subjectChip: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, borderWidth: 1 },
  sortBar:     { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4, marginBottom: 8 },
  sortLabel:   { fontSize: 11, fontFamily: 'Poppins_500Medium' },
  sortPill:    { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 14, backgroundColor: '#88888822' },
  resultCount: { fontSize: 11, fontFamily: 'Poppins_500Medium', marginTop: 4 },
  sectionTitle:{ fontSize: 17, fontFamily: 'Poppins_700Bold' },
  card:        { borderRadius: 16, padding: 16, borderWidth: 1 },
  liveBadge:   { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 12, marginBottom: 8 },
  liveBadgeText: { color: '#fff', fontSize: 10, fontFamily: 'Poppins_700Bold' },
  cardTitle:   { fontFamily: 'Poppins_600SemiBold', fontSize: 15, marginBottom: 4 },
  cardProfRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 10 },
  cardProf:    { fontFamily: 'Poppins_400Regular', fontSize: 12, flex: 1 },
  friendChip:  { flexDirection: 'row', alignItems: 'center', gap: 3, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 8 },
  friendChipText: { fontSize: 10, fontFamily: 'Poppins_600SemiBold' },
  cardMeta:    { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 10 },
  metaChip:    { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 10 },
  metaText:    { fontSize: 11, fontFamily: 'Poppins_500Medium' },
  cardBottom:  { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  ratingNum:   { fontSize: 11, fontFamily: 'Poppins_600SemiBold' },
  cardPrice:   { fontFamily: 'Poppins_700Bold', fontSize: 16 },
  loadMore:    { borderWidth: 1, borderRadius: 12, padding: 14, alignItems: 'center', marginTop: 8 },
});
