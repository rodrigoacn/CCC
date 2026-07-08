import { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Alert } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMutation } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { apiRateSession } from '@/lib/api';

export default function RateScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [rating, setRating] = useState<number>(5);

  const { mutate: rate, isLoading } = useMutation({
    mutationFn: () => apiRateSession(Number(id), rating),
    onSuccess: () => {
      router.replace('/');
    },
    onError: (e: any) => Alert.alert('Error', e.message || 'No se pudo enviar la calificación'),
  });

  return (
    <View style={[styles.page, { backgroundColor: colors.background, paddingTop: insets.top + 16 }]}> 
      <View style={{ padding: 24 }}>
        <Text style={[styles.title, { color: colors.foreground }]}>Califica la clase</Text>
        <Text style={[styles.subtitle, { color: colors.subtext }]}>Selecciona cuántas estrellas</Text>

        <View style={{ flexDirection: 'row', justifyContent: 'center', marginVertical: 20 }}>
          {[5,4,3,2,1].map(v => (
            <TouchableOpacity key={v} onPress={() => setRating(v)} style={{ marginHorizontal: 8 }}>
              <Text style={{ fontSize: 36, color: v <= rating ? colors.primary : colors.muted }}>{'★'}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <View style={{ flexDirection: 'row', gap: 12 }}>
          <TouchableOpacity style={[styles.btn, { backgroundColor: colors.muted }]} onPress={() => router.back()}>
            <Text style={{ color: colors.subtext }}>Omitir</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.btn, { backgroundColor: colors.primary }]} onPress={() => rate()} disabled={isLoading}>
            <Text style={{ color: '#fff' }}>Enviar</Text>
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
  btn: { flex: 1, paddingVertical: 14, borderRadius: 12, alignItems: 'center', marginHorizontal: 8 }
});
