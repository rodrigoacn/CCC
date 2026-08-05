import { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiRateSession } from '@/lib/api';

export default function RateScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { t } = useI18n();
  const [rating, setRating] = useState<number>(5);
  const [comentario, setComentario] = useState('');

  const { mutate: rate, isPending } = useMutation({
    mutationFn: () => apiRateSession(Number(id), rating, comentario.trim() || undefined),
    onSuccess: () => {
      router.replace('/');
    },
    onError: (e: any) => Alert.alert(t('general.error'), e.message || t('rate.error_msg')),
  });

  return (
    <View style={[styles.page, { backgroundColor: colors.background, paddingTop: insets.top + 16 }]}> 
      <TouchableOpacity
        onPress={() => router.back()}
        style={{ position: 'absolute', top: Platform.OS === 'web' ? 16 : insets.top + 8, left: 16, zIndex: 10, width: 40, height: 40, borderRadius: 20, backgroundColor: colors.muted, justifyContent: 'center', alignItems: 'center' }}
      >
        <Feather name="arrow-left" size={22} color={colors.foreground} />
      </TouchableOpacity>
      <View style={{ padding: 24 }}>
        <Text style={[styles.title, { color: colors.foreground }]}>{t('rate.title')}</Text>
        <Text style={[styles.subtitle, { color: colors.subtext }]}>{t('rate.subtitle')}</Text>

        <View style={{ flexDirection: 'row', justifyContent: 'center', marginVertical: 20 }}>
          {[5,4,3,2,1].map(v => (
            <TouchableOpacity key={v} onPress={() => setRating(v)} style={{ marginHorizontal: 8 }}>
              <Text style={{ fontSize: 36, color: v <= rating ? colors.primary : colors.muted }}>{'★'}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <TextInput
          style={[styles.commentInput, { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.muted }]}
          placeholder={t('rate.comment_placeholder')}
          placeholderTextColor={colors.subtext}
          value={comentario}
          onChangeText={setComentario}
          multiline
          maxLength={500}
        />

        <View style={{ flexDirection: 'row', gap: 12, marginTop: 16 }}>
          <TouchableOpacity style={[styles.btn, { backgroundColor: colors.muted }]} onPress={() => router.back()}>
            <Text style={{ color: colors.subtext }}>{t('rate.skip')}</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.btn, { backgroundColor: colors.primary }]} onPress={() => rate()} disabled={isPending}>
            <Text style={{ color: '#fff' }}>{t('rate.send')}</Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  page: { flex: 1 },
  title: { fontSize: 24, fontFamily: 'Poppins_700Bold' },
  subtitle: { fontSize: 14, marginTop: 6 },
  commentInput: { borderWidth: 1, borderRadius: 12, padding: 12, fontSize: 14, minHeight: 80, textAlignVertical: 'top', marginTop: 12 },
  btn: { flex: 1, paddingVertical: 14, borderRadius: 12, alignItems: 'center', marginHorizontal: 8 }
});
