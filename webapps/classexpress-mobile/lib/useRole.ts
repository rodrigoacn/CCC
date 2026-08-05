import { useEffect, useReducer } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

type Rol = 'student' | 'teacher';

let _rol: Rol = 'student';
let _initialized = false;
let _listeners: Array<() => void> = [];

function notify() {
  _listeners.forEach(fn => fn());
}

export async function initRole() {
  if (_initialized) return;
  _initialized = true;
  try {
    const val = await AsyncStorage.getItem('ce_login_rol');
    if (val === 'teacher' || val === 'student') {
      _rol = val;
      notify();
    }
  } catch {}
}

export function getRole(): Rol { return _rol; }
export function isTeacherRole(): boolean { return _rol === 'teacher'; }

export async function setRole(val: Rol) {
  _rol = val;
  await AsyncStorage.setItem('ce_login_rol', val);
  notify();
}

export function useRole() {
  const [, forceUpdate] = useReducer((x: number) => x + 1, 0);

  useEffect(() => {
    initRole();
    _listeners.push(forceUpdate);
    return () => { _listeners = _listeners.filter(fn => fn !== forceUpdate); };
  }, []);

  return { loginRol: _rol, isTeacher: _rol === 'teacher', setLoginRol: setRole };
}
