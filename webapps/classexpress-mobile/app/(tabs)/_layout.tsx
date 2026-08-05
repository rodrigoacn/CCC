import { Redirect, Tabs } from 'expo-router';
import { Platform } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useColors } from '@/hooks/useColors';
import { useAuth } from '@/context/AuthContext';
import { useRole } from '@/lib/useRole';
import { useI18n } from '@/context/I18nContext';

export default function TabsLayout() {
  const colors = useColors();
  const { user } = useAuth();
  const { isTeacher } = useRole();
  const { t } = useI18n();

  if (user?.pendingPaymentSessionId) {
    return <Redirect href={`/pago/${user.pendingPaymentSessionId}`} />;
  }

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarStyle: Platform.OS === 'web'
          ? { backgroundColor: colors.tabBar, borderTopColor: colors.border, height: 84 }
          : { backgroundColor: colors.tabBar, borderTopColor: colors.border },
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.tabBarInactive,
        tabBarLabelStyle: { fontFamily: 'Poppins_500Medium', fontSize: 11, marginBottom: Platform.OS === 'web' ? 8 : 0 },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: t('nav.materias'),
          tabBarIcon: ({ color, size }) => <Feather name="home" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="buscar"
        options={{
          title: t('nav.buscar'),
          tabBarIcon: ({ color, size }) => <Feather name="search" size={size} color={color} />,
          href: isTeacher ? null : undefined,
        }}
      />
      <Tabs.Screen
        name="sala"
        options={{
          title: t('nav.sala'),
          tabBarIcon: ({ color, size }) => <Feather name="camera" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="amigos"
        options={{
          title: t('nav.personas'),
          tabBarIcon: ({ color, size }) => <Feather name="users" size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="creditos"
        options={{
          title: t('nav.creditos'),
          tabBarIcon: ({ color, size }) => <Feather name="credit-card" size={size} color={color} />,
          href: isTeacher ? null : undefined,
        }}
      />
      <Tabs.Screen
        name="retiro"
        options={{
          title: t('retiro.withdraw'),
          tabBarIcon: ({ color, size }) => <Feather name="dollar-sign" size={size} color={color} />,
          href: isTeacher ? undefined : null,
        }}
      />
      <Tabs.Screen
        name="perfil"
        options={{
          title: t('nav.perfil'),
          tabBarIcon: ({ color, size }) => <Feather name="user" size={size} color={color} />,
        }}
      />
    </Tabs>
  );
}
