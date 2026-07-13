import { createContext, useContext, useState } from 'react';
import client from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [student, setStudent] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem('interntrack_student') || 'null');
    } catch {
      return null;
    }
  });
  const [token, setToken] = useState(() => localStorage.getItem('interntrack_token'));

  const persist = (studentData, tokenValue) => {
    localStorage.setItem('interntrack_student', JSON.stringify(studentData));
    localStorage.setItem('interntrack_token', tokenValue);
    setStudent(studentData);
    setToken(tokenValue);
  };

  const login = async (studentNumber, password) => {
    const { data } = await client.post('/auth/login', {
      student_number: studentNumber,
      password,
    });
    persist(data.student, data.token);
    return data.student;
  };

  const register = async (payload) => {
    const { data } = await client.post('/auth/register', payload);
    persist(data.student, data.token);
    return data.student;
  };

  const logout = async () => {
    try {
      await client.post('/auth/logout');
    } catch {
      // Token may already be invalid; clear local state regardless.
    }
    localStorage.removeItem('interntrack_student');
    localStorage.removeItem('interntrack_token');
    setStudent(null);
    setToken(null);
  };

  const updateStudent = (studentData) => {
    localStorage.setItem('interntrack_student', JSON.stringify(studentData));
    setStudent(studentData);
  };

  return (
    <AuthContext.Provider value={{ student, token, login, register, logout, updateStudent }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
