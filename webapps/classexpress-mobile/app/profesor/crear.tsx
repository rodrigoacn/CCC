import { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  ScrollView, Platform, Alert, ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Feather } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useColors } from '@/hooks/useColors';
import { useI18n } from '@/context/I18nContext';
import { apiCreateClass, apiSubjects, Subject } from '@/lib/api';

const DURATIONS = [30, 45, 60, 90, 120];

export default function CrearClaseScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const qc = useQueryClient();
  const { t } = useI18n();

  const [titulo, setTitulo] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [precio, setPrecio] = useState('');
  const [materiaId, setMateriaId] = useState(0);
  const [duracion, setDuracion] = useState(60);

  const { data: subjData } = useQuery({ queryKey: ['subjects'], queryFn: apiSubjects });
  const subjects = subjData?.subjects ?? [];

  const [sending, setSending] = useState(false);

  const handleSubmit = async () => {
    if (!titulo || !materiaId || !precio) {
      Alert.alert(t('create.error'));
      return;
    }
    setSending(true);
    try {
      const result = await apiCreateClass({
        titulo: titulo.trim(),
        descripcion: descripcion.trim(),
        precio: Number(precio),
        materia_id: materiaId,
        duracion,
      });
      if (__DEV__) console.log('create_class success', result);
      try { Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success); } catch {}
      qc.invalidateQueries({ queryKey: ['teacher_dashboard'] });
      if (Platform.OS === 'web') {
        window.alert(t('create.success'));
      } else {
        Alert.alert(t('create.success'));
      }
      router.push(`/materia/clase?id=${result.clase.id}`);
    } catch (e: any) {
      if (__DEV__) console.log('create_class error', e);
      if (Platform.OS === 'web') {
        window.alert(t('general.error') + ': ' + (e?.message ?? t('general.error')));
      } else {
        Alert.alert(t('general.error'), e?.message ?? t('general.error'));
      }
    } finally {
      setSending(false);
    }
  };

  const botPad = Platform.OS === 'web' ? 34 : insets.bottom;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{ padding: 20, paddingBottom: botPad + 32 }}
      keyboardShouldPersistTaps="handled"
    >
      <Text style={[styles.label, { color: colors.subtext }]}>{t('create.class_title')}</Text>
      <TextInput
        style={[styles.input, { backgroundColor: colors.muted, color: colors.foreground }]}
        placeholder={t('create.class_placeholder')}
        placeholderTextColor={colors.mutedForeground}
        value={titulo}
        onChangeText={setTitulo}
      />

      <Text style={[styles.label, { color: colors.subtext }]}>{t('create.description')}</Text>
      <TextInput
        style={[styles.input, { backgroundColor: colors.muted, color: colors.foreground, height: 100, textAlignVertical: 'top' }]}
        placeholder={t('create.desc_placeholder')}
        placeholderTextColor={colors.mutedForeground}
        value={descripcion}
        onChangeText={setDescripcion}
        multiline
        numberOfLines={4}
      />

      <Text style={[styles.label, { color: colors.subtext }]}>{t('create.subject')}</Text>
      <View style={styles.chipWrap}>
        {subjects.map((s: Subject) => (
          <TouchableOpacity
            key={s.id}
            style={[styles.chip, { backgroundColor: materiaId === s.id ? colors.primary : colors.muted }]}
            onPress={() => setMateriaId(s.id)}
          >
            <Text style={[styles.chipTxt, { color: materiaId === s.id ? '#fff' : colors.foreground }]}>{s.nombre}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <Text style={[styles.label, { color: colors.subtext }]}>{t('create.price')}</Text>
      <TextInput
        style={[styles.input, { backgroundColor: colors.muted, color: colors.foreground }]}
        placeholder={t('create.price_placeholder')}
        placeholderTextColor={colors.mutedForeground}
        value={precio}
        onChangeText={setPrecio}
        keyboardType="numeric"
      />

      <Text style={[styles.label, { color: colors.subtext }]}>{t('create.duration')}</Text>
      <View style={styles.chipWrap}>
        {DURATIONS.map(d => (
          <TouchableOpacity
            key={d}
            style={[styles.chip, { backgroundColor: duracion === d ? colors.primary : colors.muted }]}
            onPress={() => setDuracion(d)}
          >
            <Text style={[styles.chipTxt, { color: duracion === d ? '#fff' : colors.foreground }]}>{d} min</Text>
          </TouchableOpacity>
        ))}
      </View>

      <TouchableOpacity
        style={[styles.btn, { backgroundColor: sending ? colors.muted : colors.primary, marginTop: 24 }]}
        onPress={handleSubmit}
        disabled={sending}
      >
        {sending ? <ActivityIndicator color="#fff" /> : (
          <>
            <Feather name="plus-circle" size={20} color="#fff" />
            <Text style={styles.btnTxt}>{t('create.publish')}</Text>
          </>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  label:    { fontFamily: 'Poppins_600SemiBold', fontSize: 13, marginBottom: 8, marginTop: 16 },
  input:    { borderRadius: 14, paddingHorizontal: 16, paddingVertical: 13, fontSize: 15, fontFamily: 'Poppins_400Regular', marginBottom: 4 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 4 },
  chip:     { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20 },
  chipTxt:  { fontFamily: 'Poppins_500Medium', fontSize: 13 },
  btn:      { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, paddingVertical: 16, borderRadius: 16 },
  btnTxt:   { color: '#fff', fontFamily: 'Poppins_700Bold', fontSize: 16 },
});
