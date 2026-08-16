# Report 7: React.js Frontend Integration Guide

This guide outlines the architecture for building the frontend portfolio submission wizard in React.js using **React Hook Form**, **Axios**, and **Tailwind CSS**.

## 1. Component Architecture & Multi-Step Wizard
To provide an exceptional user experience without overwhelming the student intern, the 271 placeholders are organized into a 6-step form wizard:
1. **Step 1: Student & Cover Information** (`CoverSection.jsx`)
2. **Step 2: University & Company Profile** (`CompanyProfileSection.jsx`)
3. **Step 3: Weekly Progress Reports (Weeks 1–16)** (`WeeklyWizard.jsx` with tabbed week switching)
4. **Step 4: Chapter III Program Assessment** (`AssessmentSection.jsx`)
5. **Step 5: Appendices Document Upload** (`AppendixUploader.jsx`)
6. **Step 6: Review & Generate Portfolio** (`ReviewSubmit.jsx`)

## 2. Sample Form Component with React Hook Form & Axios

```jsx
import React, { useState } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import axios from 'axios';

export default function PortfolioSubmissionWizard() {
  const { register, handleSubmit, control, formState: { errors }, watch } = useForm({
    defaultValues: {
      studentName: '',
      studentNumber: '',
      course: 'Bachelor of Science in Computer Science',
      section: '',
      instructorName: '',
      submissionDate: new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
      companyName: '',
      companyAddress: '',
      ucVision: 'To be a premier institution of higher learning in the region...',
      ucMission: 'To provide quality academic programs and holistic student development...',
      // Initialize 16 weeks
      weeklyReports: Array.from({ length: 16 }, (_, i) => ({
        weekNumber: i + 1,
        startDate: '',
        endDate: '',
        objectives: '',
        tasks: '',
        skills: '',
        problems: '',
        solutions: '',
        reflection: '',
        facultyRemarks: 'N/A',
        supervisorRemarks: 'N/A'
      }))
    }
  });

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [downloadUrl, setDownloadUrl] = useState(null);

  const onSubmit = async (data) => {
    setIsSubmitting(true);
    const formData = new FormData();

    // Append standard text fields
    formData.append('student_name', data.studentName);
    formData.append('student_number', data.studentNumber);
    formData.append('course', data.course);
    formData.append('section', data.section);
    formData.append('instructor_name', data.instructorName);
    formData.append('submission_date', data.submissionDate);
    formData.append('company_name', data.companyName);
    formData.append('company_address', data.companyAddress);
    formData.append('company_history', data.companyHistory);
    formData.append('vision_mission', data.companyVisionMission);
    formData.append('uc_vision', data.ucVision);
    formData.append('uc_mission', data.ucMission);

    // Append file uploads if present
    if (data.studentPhoto?.[0]) formData.append('student_photo', data.studentPhoto[0]);
    if (data.companyLogo?.[0]) formData.append('company_logo', data.companyLogo[0]);
    if (data.orgChart?.[0]) formData.append('org_chart', data.orgChart[0]);

    // Append weekly reports
    data.weeklyReports.forEach((week, index) => {
      const w = index + 1;
      formData.append(`week${w}_start_date`, week.startDate);
      formData.append(`week${w}_end_date`, week.endDate);
      formData.append(`week${w}_objectives`, week.objectives);
      formData.append(`week${w}_tasks`, week.tasks);
      formData.append(`week${w}_skills`, week.skills);
      formData.append(`week${w}_problems`, week.problems || '');
      formData.append(`week${w}_solutions`, week.solutions || '');
      formData.append(`week${w}_reflection`, week.reflection);
      formData.append(`week${w}_faculty_remarks`, week.facultyRemarks || 'N/A');
      formData.append(`week${w}_supervisor_remarks`, week.supervisorRemarks || 'N/A');
    });

    try {
      const response = await axios.post('/api/v1/portfolios/generate', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        responseType: 'blob' // Important for downloading generated PDF/DOCX
      });

      // Create blob download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `${data.studentNumber}_Internship_Portfolio.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (error) {
      console.error('Portfolio generation failed:', error);
      alert('Error generating portfolio. Please check console for details.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto p-8 bg-white shadow-xl rounded-2xl border border-gray-100">
      <h1 className="text-3xl font-extrabold text-gray-900 mb-6 border-b pb-4">
        INTERNTRACK Portfolio Submission Wizard
      </h1>
      
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-8">
        {/* Step 1: Cover Information */}
        <div className="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-4">
          <h2 className="text-xl font-bold text-indigo-700">Step 1: Student & Cover Information</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">Full Name</label>
              <input 
                {...register('studentName', { required: 'Student name is required' })}
                placeholder="DELA CRUZ, JUAN X."
                className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
              {errors.studentName && <span className="text-red-500 text-xs">{errors.studentName.message}</span>}
            </div>
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">Student Number</label>
              <input 
                {...register('studentNumber', { required: 'Student number is required' })}
                placeholder="2022-12345"
                className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
              {errors.studentNumber && <span className="text-red-500 text-xs">{errors.studentNumber.message}</span>}
            </div>
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">Course / Degree Program</label>
              <input 
                {...register('course', { required: true })}
                className="w-full p-3 border border-gray-300 rounded-lg bg-gray-100"
                readOnly
              />
            </div>
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">Section</label>
              <input 
                {...register('section', { required: 'Section is required' })}
                placeholder="4IT-A"
                className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>
        </div>

        {/* Submit Action */}
        <div className="flex justify-end pt-6 border-t">
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl shadow-lg hover:from-indigo-700 hover:to-purple-700 transition duration-200 disabled:opacity-50"
          >
            {isSubmitting ? 'Generating Official Portfolio...' : 'Generate & Download Portfolio (PDF)'}
          </button>
        </div>
      </form>
    </div>
  );
}
```
