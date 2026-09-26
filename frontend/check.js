import React from "react";
import { renderToString } from "react-dom/server";
import { MemoryRouter } from "react-router-dom";
import FacultyAssignedStudents from "./src/pages/faculty/FacultyAssignedStudents";
import { AuthProvider } from "./src/contexts/AuthContext";

try {
  const html = renderToString(
    <MemoryRouter>
      <AuthProvider>
        <FacultyAssignedStudents />
      </AuthProvider>
    </MemoryRouter>
  );
  console.log("RENDER SUCCESS", html.length);
} catch (e) {
  console.error("RENDER ERROR:", e);
}
