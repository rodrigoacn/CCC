import { useState, useEffect } from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet, Platform, ActivityIndicator } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { apiUserProfile, apiFollow, apiUnfriend, UserProfile, Resena } from '@/lib/api';

export default function PerfilUsuarioScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [siguiendo, setSiguiendo] = useState(false);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    apiUserProfile(Number(id))
      .then(res => {
        setProfile(res.profile);
        setSiguiendo(res.profile.siguiendo);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [id]);

  const toggleFollow = async () => {
    if (!profile) return;
    try {
      const res = await apiFollow(profile.id);
      setSiguiendo(res.siguiendo);
    } catch {}
  };

  const isMe = user?.id === Number(id);
  const esProfesor = profile?.rol === 'instructor' || profile?.rol === 'both';
  const topPad = Platform.OS === 'web' ? 67 : insets.top;

  if (loading) {
    return (
      <View style={[styles.page, { backgroundColor: colors.background, paddingTop: topPad + 20 }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!profile) {
    return (
      <View style={[styles.page, { backgroundColor: colors.background, paddingTop: topPad + 20 }]}>
        <Text style={{ color: colors.subtext, textAlign: 'center' }}>Usuario no encontrado</Text>
      </View>
    );
  }

  return (
    <View style={[styles.page, { backgroundColor: colors.background }]}>
      <FlatList
        data={profile.resenas}
        keyExtractor={i => String(i.resenaId)}
        contentContainerStyle={{ paddingBottom: insets.bottom + 40 }}
        ListHeaderComponent={
          <View>
            {/* Header */}
            <View style={{ paddingHorizontal: 20, paddingTop: topPad + 12, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: colors.border }}>
              <TouchableOpacity onPress={() => router.back()} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 16 }}>
                <Feather name="arrow-left" size={18} color={colors.foreground} />
                <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 13 }}>Personas</Text>
              </TouchableOpacity>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
                <View style={[styles.avatar, { backgroundColor: colors.primaryLight }]}>
                  {profile.avatar ? (
                    <View />
                  ) : (
                    <Text style={[styles.avatarLetter, { color: colors.primary }]}>
                      {profile.nombre?.[0]?.toUpperCase() ?? '?'}
                    </Text>
                  )}
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.name, { color: colors.foreground }]}>{profile.nombre}</Text>
                  <Text style={[styles.username, { color: colors.subtext }]}>@{profile.username}</Text>
                  <Text style={[styles.role, { color: colors.primary }]}>{esProfesor ? 'Profesor' : 'Estudiante'}</Text>
                </View>
                {!isMe && (
                  <View style={{ gap: 6 }}>
                    <TouchableOpacity
                      style={[styles.followBtn, { backgroundColor: siguiendo ? colors.muted : colors.primary }]}
                      onPress={toggleFollow}
                    >
                      <Text style={{ color: siguiendo ? colors.foreground : '#fff', fontFamily: 'Poppins_600SemiBold', fontSize: 12 }}>
                        {siguiendo ? 'Dejar de seguir' : 'Seguir'}
                      </Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.followBtn, { backgroundColor: colors.muted, borderWidth: 1, borderColor: colors.primary }]}
                      onPress={() => router.push(`/personas?chat=${profile.id}` as any)}
                    >
                      <Text style={{ color: colors.primary, fontFamily: 'Poppins_600SemiBold', fontSize: 12 }}>Mensaje</Text>
                    </TouchableOpacity>
                  </View>
                )}
              </View>
            </View>

            {/* Bio */}
            {profile.biografia ? (
              <View style={{ padding: 20, borderBottomWidth: 1, borderBottomColor: colors.border }}>
                <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 13, lineHeight: 20 }}>{profile.biografia}</Text>
              </View>
            ) : null}

            {/* Info */}
            <View style={{ padding: 20, borderBottomWidth: 1, borderBottomColor: colors.border }}>
              <Text style={{ color: colors.foreground, fontFamily: 'Poppins_400Regular', fontSize: 13, lineHeight: 22 }}>
                {profile.pais ? <Text><Text style={{ color: colors.subtext }}>País: </Text>{profile.pais}{'\n'}</Text> : null}
                {profile.idiomas?.length > 0 ? <Text><Text style={{ color: colors.subtext }}>Idiomas: </Text>{profile.idiomas.join(', ')}{'\n'}</Text> : null}
                <Text><Text style={{ color: colors.subtext }}>Miembro desde: </Text>{new Date(profile.created_at).toLocaleDateString('es', { month: 'short', year: 'numeric' })}</Text>
              </Text>
            </View>

            {/* Rating (teachers only) */}
            {esProfesor && (
              <View style={{ padding: 20, borderBottomWidth: 1, borderBottomColor: colors.border }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                  <Text style={{ fontSize: 36, fontFamily: 'Poppins_700Bold', color: colors.primary }}>{profile.calificacion.toFixed(1)}</Text>
                  <View>
                    <Text style={{ fontSize: 14, color: colors.primary }}>{'★'.repeat(5)}</Text>
                    <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 12 }}>{profile.num_resenas} reseñas</Text>
                  </View>
                </View>
              </View>
            )}

            {/* Teacher's upcoming classes */}
            {esProfesor && profile.clases && profile.clases.length > 0 && (
              <View style={{ padding: 20, borderBottomWidth: 1, borderBottomColor: colors.border }}>
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold', fontSize: 14, marginBottom: 12 }}>
                  Clases disponibles ({profile.clases.length})
                </Text>
                {profile.clases.map(c => (
                  <TouchableOpacity
                    key={c.id}
                    style={{ flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.border + '44' }}
                    onPress={() => router.push(`/materia/clase?id=${c.id}` as any)}
                  >
                    <View style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: c.color || colors.primaryLight, alignItems: 'center', justifyContent: 'center' }}>
                      <Text style={{ fontSize: 16 }}>{c.icono || '📚'}</Text>
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={{ color: colors.foreground, fontFamily: 'Poppins_500Medium', fontSize: 13 }} numberOfLines={1}>{c.titulo}</Text>
                      <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 11 }}>
                        {c.materia} · ${Number(c.precio_base).toFixed(2)} · {c.duracion}min{c.alumnos_activos > 0 ? ` · ${c.alumnos_activos} en clase` : ''}
                      </Text>
                    </View>
                    <Feather name="chevron-right" size={16} color={colors.subtext} />
                  </TouchableOpacity>
                ))}
              </View>
            )}

            {/* Reviews header */}
            {esProfesor && (
              <View style={{ paddingHorizontal: 20, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: colors.border }}>
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold', fontSize: 14 }}>
                  Reseñas ({profile.resenas.length})
                </Text>
              </View>
            )}
          </View>
        }
        ListEmptyComponent={
          esProfesor ? (
            <View style={{ alignItems: 'center', paddingTop: 40 }}>
              <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular' }}>Aún no tiene reseñas</Text>
            </View>
          ) : null
        }
        renderItem={({ item }) => (
          <View style={{ padding: 16, borderBottomWidth: 1, borderBottomColor: colors.border }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 6 }}>
              <View style={[styles.avatarSm, { backgroundColor: colors.primaryLight }]}>
                <Text style={[styles.avatarLetterSm, { color: colors.primary }]}>
                  {item.estudiante_nombre?.[0]?.toUpperCase() ?? '?'}
                </Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ color: colors.foreground, fontFamily: 'Poppins_600SemiBold', fontSize: 13 }}>{item.estudiante_nombre}</Text>
                <Text style={{ color: colors.primary, fontFamily: 'Poppins_400Regular', fontSize: 11 }}>
                  {Array(item.rating).fill('★').join('')}{Array(5 - item.rating).fill('☆').join('')}
                </Text>
              </View>
              <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 10 }}>
                {new Date(item.created_at).toLocaleDateString('es')}
              </Text>
            </View>
            {item.comentario ? (
              <Text style={{ color: colors.subtext, fontFamily: 'Poppins_400Regular', fontSize: 13, marginLeft: 42, lineHeight: 18 }}>
                {item.comentario}
              </Text>
            ) : null}
          </View>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  page: { flex: 1 },
  avatar: { width: 64, height: 64, borderRadius: 32, justifyContent: 'center', alignItems: 'center' },
  avatarLetter: { fontFamily: 'Poppins_700Bold', fontSize: 24 },
  name: { fontFamily: 'Poppins_700Bold', fontSize: 20 },
  username: { fontFamily: 'Poppins_400Regular', fontSize: 13, marginTop: 2 },
  role: { fontFamily: 'Poppins_600SemiBold', fontSize: 13, marginTop: 2 },
  followBtn: { paddingVertical: 6, paddingHorizontal: 16, borderRadius: 20, alignItems: 'center' },
  avatarSm: { width: 32, height: 32, borderRadius: 16, justifyContent: 'center', alignItems: 'center' },
  avatarLetterSm: { fontFamily: 'Poppins_700Bold', fontSize: 13 },
});
