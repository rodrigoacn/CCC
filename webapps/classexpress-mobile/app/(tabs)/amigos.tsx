import { useState, useRef, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet, Platform,
  ActivityIndicator, TextInput, Alert, KeyboardAvoidingView,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { useI18n } from '@/context/I18nContext';
import { apiFriends, apiFollow, apiUnfriend, apiSendDirectMessage, apiGetDirectMessages, apiSearchPeople, apiUserProfile, FriendUser, DirectMessage, PersonaResult, esInstructor, ProfileClase } from '@/lib/api';

const QUICK_MSGS_KEYS = [
  'friends.quick_when',
  'friends.quick_today',
  'friends.quick_time',
  'friends.quick_want',
  'friends.quick_great',
  'friends.quick_avail',
];

export default function AmigosScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const router = useRouter();
  const { t } = useI18n();
  const [tab, setTab] = useState<'siguiendo' | 'seguidores'>('siguiendo');
  const [chatWith, setChatWith] = useState<FriendUser | null>(null);
  const [dmText, setDmText] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<PersonaResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [teacherClasses, setTeacherClasses] = useState<ProfileClase[]>([]);
  const [dmError, setDmError] = useState('');
  const flatRef = useRef<FlatList>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['friends'],
    queryFn: apiFriends,
  });

  const { data: dmData, refetch: refetchDms } = useQuery({
    queryKey: ['dms', chatWith?.usuarioid],
    queryFn: () => apiGetDirectMessages(chatWith?.usuarioid),
    enabled: !!chatWith,
    refetchInterval: 3000,
  });

  const followMut = useMutation({
    mutationFn: (id: number) => apiFollow(id),
    onSuccess: () => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      queryClient.invalidateQueries({ queryKey: ['friends'] });
    },
  });

  const unfriendMut = useMutation({
    mutationFn: (id: number) => apiUnfriend(id),
    onSuccess: () => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      queryClient.invalidateQueries({ queryKey: ['friends'] });
    },
  });

  const sendDmMut = useMutation({
    mutationFn: ({ to, msg }: { to: number; msg: string }) => apiSendDirectMessage(to, msg),
    onSuccess: () => {
      setDmText('');
      setDmError('');
      refetchDms();
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      setTimeout(() => flatRef.current?.scrollToEnd({ animated: true }), 100);
    },
    onError: (e: any) => {
      if (__DEV__) console.log('send DM error', e);
      setDmError(t('friends.dm_error'));
    },
  });

  const siguiendo: FriendUser[] = data?.siguiendo ?? [];
  const seguidores: FriendUser[] = data?.seguidores ?? [];
  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  const activeList = searchResults.length > 0 ? [] : (tab === 'siguiendo' ? siguiendo : seguidores);

  const doSearch = async (q: string) => {
    if (q.length < 1) { setSearchResults([]); return; }
    setSearching(true);
    try {
      const res = await apiSearchPeople(q);
      setSearchResults(res.people ?? []);
    } catch { setSearchResults([]); }
    setSearching(false);
  };

  function openChat(friend: FriendUser) {
    setChatWith(friend);
    setDmText('');
    setTeacherClasses([]);
    refetchDms();
    // Fetch teacher's classes if they are an instructor
    if (esInstructor(friend.rol)) {
      apiUserProfile(friend.usuarioid).then(res => {
        if (res.profile.clases?.length) setTeacherClasses(res.profile.clases);
      }).catch(() => {});
    }
  }

  const renderFriendItem = (item: FriendUser) => {
    const isMe = item.usuarioid === user?.id;
    const yoLoSigo = siguiendo.some(s => s.usuarioid === item.usuarioid);
    const elMeSigue = seguidores.some(s => s.usuarioid === item.usuarioid);
    return (
      <View style={[styles.friendRow, { borderBottomColor: colors.border }]}>
        <View style={[styles.avatar, { backgroundColor: colors.primaryLight }]}>
          <Text style={[styles.avatarLetter, { color: colors.primary }]}>
            {item.nombre?.[0]?.toUpperCase() ?? '?'}
          </Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={[styles.friendName, { color: colors.foreground }]}>{item.nombre}</Text>
          <Text style={[styles.friendUser, { color: colors.subtext }]}>@{item.username} · {esInstructor(item.rol) ? t('friends.profesor') : t('friends.estudiante')}</Text>
          {item.calificacion && Number(item.calificacion) > 0 && (
            <Text style={{ color: colors.primary, fontFamily: 'Poppins_400Regular', fontSize: 11, marginTop: 1 }}>
              ★ {Number(item.calificacion).toFixed(1)} ({item.num_resenas ?? 0})
            </Text>
          )}
        </View>
        {!isMe && (
          <View style={{ flexDirection: 'row', gap: 6 }}>
            <TouchableOpacity style={[styles.smallBtn, { backgroundColor: colors.primary }]} onPress={() => openChat(item)}>
              <Feather name="message-circle" size={16} color="#fff" />
            </TouchableOpacity>
            {!yoLoSigo ? (
              <TouchableOpacity style={[styles.smallBtn, { backgroundColor: colors.success }]} onPress={() => followMut.mutate(item.usuarioid)}>
                <Feather name="user-plus" size={16} color="#fff" />
              </TouchableOpacity>
            ) : (
              <TouchableOpacity style={[styles.smallBtn, { backgroundColor: colors.mutedForeground }]} onPress={() => {
                if (Platform.OS === 'web') {
                  if (window.confirm(t('friends.unfollow_msg', { name: item.nombre }))) {
                    unfriendMut.mutate(item.usuarioid);
                  }
                } else {
                  Alert.alert(t('friends.unfollow_title'), t('friends.unfollow_msg', { name: item.nombre }), [
                    { text: t('friends.no'), style: 'cancel' },
                    { text: t('friends.yes'), style: 'destructive', onPress: () => unfriendMut.mutate(item.usuarioid) },
                  ]);
                }
              }}>
                <Feather name="user-minus" size={16} color="#fff" />
              </TouchableOpacity>
            )}
          </View>
        )}
      </View>
    );
  };

  // Chat Bubble
  const renderChatBubble = (msg: DirectMessage) => {
    const mine = msg.remitente_id === user?.id;
    return (
      <View key={msg.id} style={[styles.bubble, mine ? styles.bubbleMine : styles.bubbleOther, { backgroundColor: mine ? colors.primary : colors.muted }]}>
        <Text style={[styles.bubbleTxt, { color: mine ? '#fff' : colors.foreground }]}>{msg.mensaje}</Text>
        <Text style={[styles.bubbleTime, { color: mine ? 'rgba(255,255,255,0.6)' : colors.subtext }]}>
          {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
        </Text>
      </View>
    );
  };

  if (chatWith) {
    const mensajes: DirectMessage[] = dmData?.mensajes ?? [];
    return (
      <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.background }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={[styles.chatHeader, { paddingTop: topPad + 8, backgroundColor: colors.surface, borderBottomColor: colors.border }]}>
          <TouchableOpacity onPress={() => setChatWith(null)} style={{ padding: 4 }}>
            <Feather name="arrow-left" size={24} color={colors.foreground} />
          </TouchableOpacity>
          <View style={[styles.avatarSm, { backgroundColor: colors.primaryLight }]}>
            <Text style={[styles.avatarLetterSm, { color: colors.primary }]}>
              {chatWith.nombre?.[0]?.toUpperCase() ?? '?'}
            </Text>
          </View>
          <Text style={[styles.chatName, { color: colors.foreground }]}>{chatWith.nombre}</Text>
        </View>

        {/* Teacher's available classes */}
        {teacherClasses.length > 0 && (
          <View style={{ paddingHorizontal: 16, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.border }}>
            <Text style={{ fontFamily: 'Poppins_600SemiBold', fontSize: 13, color: colors.foreground, marginBottom: 6 }}>
              {t('friends.classes_available')} ({teacherClasses.length})
            </Text>
            {teacherClasses.map(c => (
              <TouchableOpacity
                key={c.id}
                style={{ flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8, paddingHorizontal: 12, borderRadius: 12, backgroundColor: colors.muted, marginBottom: 4 }}
                onPress={() => router.push(`/materia/clase?id=${c.id}` as any)}
              >
                <Text style={{ fontSize: 16 }}>{c.icono || '📚'}</Text>
                <Text style={{ flex: 1, fontFamily: 'Poppins_500Medium', fontSize: 13, color: colors.foreground }} numberOfLines={1}>{c.titulo}</Text>
                <Text style={{ fontFamily: 'Poppins_600SemiBold', fontSize: 13, color: colors.primary }}>${Number(c.precio_base).toFixed(2)}</Text>
                {c.alumnos_activos > 0 && (
                  <Text style={{ fontFamily: 'Poppins_400Regular', fontSize: 11, color: colors.success }}>🔴 {c.alumnos_activos}</Text>
                )}
              </TouchableOpacity>
            ))}
          </View>
        )}

        <FlatList
          ref={flatRef}
          data={mensajes}
          keyExtractor={i => String(i.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 16 }}
          ListEmptyComponent={
            <View style={{ alignItems: 'center', paddingTop: 40 }}>
              <Feather name="message-circle" size={36} color={colors.mutedForeground} />
              <Text style={{ color: colors.subtext, marginTop: 8, fontFamily: 'Poppins_400Regular' }}>
                {t('friends.send_dm')}
              </Text>
            </View>
          }
          renderItem={({ item }) => renderChatBubble(item)}
          onContentSizeChange={() => mensajes.length > 0 && flatRef.current?.scrollToEnd({ animated: true })}
        />

        {/* Quick messages */}
        <View style={{ paddingHorizontal: 16, paddingBottom: 4 }}>
          <FlatList
            horizontal
            data={QUICK_MSGS_KEYS}
            keyExtractor={i => i}
            showsHorizontalScrollIndicator={false}
            renderItem={({ item }) => (
              <TouchableOpacity
                style={[styles.quickChip, { backgroundColor: colors.muted }]}
                onPress={() => sendDmMut.mutate({ to: chatWith.usuarioid, msg: t(item) })}
              >
                <Text style={[styles.quickChipTxt, { color: colors.primary }]}>{t(item)}</Text>
              </TouchableOpacity>
            )}
          />
        </View>

        <View style={{ paddingHorizontal: 16, paddingTop: 4 }}>
          {dmError ? (
            <Text style={{ color: colors.danger, fontFamily: 'Poppins_400Regular', fontSize: 12, textAlign: 'center' }}>
              {dmError}
            </Text>
          ) : null}
        </View>
        <View style={[styles.dmInputRow, { paddingBottom: insets.bottom + 8, backgroundColor: colors.surface, borderTopColor: colors.border }]}>
          <TextInput
            style={[styles.dmInput, { backgroundColor: colors.muted, color: colors.foreground }]}
            placeholder={t('friends.type_msg')}
            placeholderTextColor={colors.mutedForeground}
            value={dmText}
            onChangeText={setDmText}
            onSubmitEditing={() => { if (dmText.trim()) sendDmMut.mutate({ to: chatWith.usuarioid, msg: dmText.trim() }); }}
            returnKeyType="send"
          />
          <TouchableOpacity
            style={[styles.sendBtn, { backgroundColor: dmText.trim() ? colors.primary : colors.muted }]}
            onPress={() => { if (dmText.trim()) sendDmMut.mutate({ to: chatWith.usuarioid, msg: dmText.trim() }); }}
            disabled={!dmText.trim()}
          >
            <Feather name="send" size={18} color={dmText.trim() ? '#fff' : colors.mutedForeground} />
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <FlatList
        data={activeList}
        keyExtractor={i => String(i.usuarioid)}
        contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
        ListHeaderComponent={
          <View>
            <View style={[styles.header, { paddingTop: topPad + 12 }]}>
              <Text style={[styles.headTitle, { color: colors.foreground }]}>{t('friends.title')}</Text>
              <Text style={[styles.headSub, { color: colors.subtext }]}>{t('friends.subtitle')}</Text>
            </View>

            {/* Search bar */}
            <View style={{ marginHorizontal: 20, marginBottom: 12 }}>
              <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center' }}>
                <TextInput
                  style={[styles.searchInput, { backgroundColor: colors.muted, color: colors.foreground, borderColor: colors.border }]}
                  placeholder={t('friends.search_placeholder')}
                  placeholderTextColor={colors.mutedForeground}
                  value={searchQuery}
                  onChangeText={(txt) => { setSearchQuery(txt); doSearch(txt); }}
                  onSubmitEditing={() => doSearch(searchQuery)}
                  returnKeyType="search"
                />
                <TouchableOpacity
                  style={[styles.searchBtn, { backgroundColor: colors.primary }]}
                  onPress={() => doSearch(searchQuery)}
                >
                  <Feather name="search" size={18} color="#fff" />
                </TouchableOpacity>
              </View>
            </View>

            {/* Search Results */}
            {searchResults.length > 0 && (
              <View style={{ marginBottom: 12 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, marginBottom: 8 }}>
                  <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold', fontSize: 13 }}>{t('friends.search_results')}</Text>
                  <TouchableOpacity onPress={() => { setSearchQuery(''); setSearchResults([]); }}>
                    <Text style={{ color: colors.primary, fontFamily: 'Poppins_400Regular', fontSize: 12 }}>{t('friends.clear')}</Text>
                  </TouchableOpacity>
                </View>
                {searchResults.map(p => {
                  const esProf = p.rol === 'instructor' || p.rol === 'both';
                  return (
                    <TouchableOpacity
                      key={p.id}
                      style={[styles.friendRow, { borderBottomColor: colors.border }]}
                      onPress={() => router.push(`/perfil_usuario/${p.id}` as any)}
                    >
                      <View style={[styles.avatar, { backgroundColor: colors.primaryLight }]}>
                        <Text style={[styles.avatarLetter, { color: colors.primary }]}>{p.nombre?.[0]?.toUpperCase() ?? '?'}</Text>
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.friendName, { color: colors.foreground }]}>{p.nombre}</Text>
                        <Text style={[styles.friendUser, { color: colors.subtext }]}>@{p.username} · {esProf ? t('friends.profesor') : t('friends.estudiante')}{p.pais ? ` · ${p.pais}` : ''}</Text>
                        {p.rating ? <Text style={{ color: colors.primary, fontFamily: 'Poppins_400Regular', fontSize: 11 }}>★ {p.rating.toFixed(1)} ({p.reviews})</Text> : null}
                      </View>
                      <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                    </TouchableOpacity>
                  );
                })}
              </View>
            )}

            {searching && (
              <View style={{ paddingVertical: 10, alignItems: 'center' }}>
                <ActivityIndicator color={colors.primary} />
              </View>
            )}

            {searchResults.length === 0 && !searchQuery.trim() ? (
            <>            <View style={styles.statsRow}>
              <View style={[styles.statCard, { backgroundColor: colors.card }]}>
                <Text style={[styles.statNum, { color: colors.primary }]}>{siguiendo.length}</Text>
                <Text style={[styles.statLabel, { color: colors.subtext }]}>{t('friends.following')}</Text>
              </View>
              <View style={[styles.statCard, { backgroundColor: colors.card }]}>
                <Text style={[styles.statNum, { color: colors.primary }]}>{seguidores.length}</Text>
                <Text style={[styles.statLabel, { color: colors.subtext }]}>{t('friends.followers')}</Text>
              </View>
            </View>

            {/* Tabs */}
            <View style={{ flexDirection: 'row', marginHorizontal: 20, marginBottom: 12, gap: 8 }}>
              {(['siguiendo', 'seguidores'] as const).map(tabKey => (
                <TouchableOpacity
                  key={tabKey}
                  style={[styles.tabBtn, { backgroundColor: tab === tabKey ? colors.primary : colors.muted }]}
                  onPress={() => setTab(tabKey)}
                >
                  <Text style={{ color: tab === tabKey ? '#fff' : colors.subtext, fontFamily: 'Poppins_600SemiBold', fontSize: 13 }}>
                    {tabKey === 'siguiendo' ? `${t('friends.following')} (${siguiendo.length})` : `${t('friends.followers')} (${seguidores.length})`}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
            </>) : null}

            {isLoading && (
              <View style={{ paddingVertical: 20, alignItems: 'center' }}>
                <ActivityIndicator color={colors.primary} />
              </View>
            )}
          </View>
        }
        ListEmptyComponent={
          !isLoading ? (
            <View style={{ alignItems: 'center', paddingTop: 40 }}>
              <Feather name="users" size={40} color={colors.mutedForeground} />
              <Text style={{ color: colors.subtext, marginTop: 12, fontFamily: 'Poppins_400Regular', textAlign: 'center', paddingHorizontal: 40 }}>
                {tab === 'siguiendo' ? t('friends.empty_following') :
                 t('friends.empty_followers')}
              </Text>
            </View>
          ) : null
        }
        renderItem={({ item }) => renderFriendItem(item)}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  header:       { paddingHorizontal: 20, paddingBottom: 10 },
  searchInput:  { flex: 1, borderRadius: 24, paddingHorizontal: 16, paddingVertical: 10, fontFamily: 'Poppins_400Regular', fontSize: 14, borderWidth: 1 },
  searchBtn:    { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center' },
  headTitle:    { fontSize: 26, fontFamily: 'Poppins_700Bold' },
  headSub:      { fontSize: 13, fontFamily: 'Poppins_400Regular', marginTop: 4 },

  statsRow:     { flexDirection: 'row', gap: 8, marginHorizontal: 20, marginBottom: 12 },
  statCard:     { flex: 1, borderRadius: 14, padding: 14, alignItems: 'center' },
  statNum:      { fontFamily: 'Poppins_700Bold', fontSize: 24 },
  statLabel:    { fontFamily: 'Poppins_400Regular', fontSize: 11, marginTop: 2 },
  tabBtn:       { flex: 1, paddingVertical: 10, borderRadius: 12, alignItems: 'center' },
  friendRow:    { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 20, borderBottomWidth: 1 },
  avatar:       { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center' },
  avatarLetter: { fontFamily: 'Poppins_700Bold', fontSize: 18 },
  friendName:   { fontFamily: 'Poppins_600SemiBold', fontSize: 15 },
  friendUser:   { fontFamily: 'Poppins_400Regular', fontSize: 12 },
  smallBtn:     { width: 34, height: 34, borderRadius: 17, justifyContent: 'center', alignItems: 'center' },
  // Chat styles
  chatHeader:   { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 16, paddingBottom: 10, borderBottomWidth: 1 },
  avatarSm:     { width: 36, height: 36, borderRadius: 18, justifyContent: 'center', alignItems: 'center' },
  avatarLetterSm: { fontFamily: 'Poppins_700Bold', fontSize: 15 },
  chatName:     { fontFamily: 'Poppins_700Bold', fontSize: 17, flex: 1 },
  bubble:       { maxWidth: '80%', borderRadius: 18, paddingHorizontal: 14, paddingVertical: 8, marginBottom: 8 },
  bubbleMine:   { alignSelf: 'flex-end', borderBottomRightRadius: 4 },
  bubbleOther:  { alignSelf: 'flex-start', borderBottomLeftRadius: 4 },
  bubbleTxt:    { fontFamily: 'Poppins_400Regular', fontSize: 14 },
  bubbleTime:   { fontFamily: 'Poppins_400Regular', fontSize: 10, marginTop: 2, alignSelf: 'flex-end' },
  quickChip:    { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8, marginBottom: 8 },
  quickChipTxt: { fontFamily: 'Poppins_500Medium', fontSize: 12 },
  dmInputRow:   { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 16, paddingTop: 8, borderTopWidth: 1 },
  dmInput:      { flex: 1, borderRadius: 24, paddingHorizontal: 16, paddingVertical: 10, fontFamily: 'Poppins_400Regular', fontSize: 14 },
  sendBtn:      { width: 44, height: 44, borderRadius: 22, justifyContent: 'center', alignItems: 'center' },
});
