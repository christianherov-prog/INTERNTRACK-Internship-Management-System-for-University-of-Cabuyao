import os
import re

controllers = [
    r'c:\Users\Hero\OneDrive\Desktop\CAPSTONE FINAL\backend\app\Http\Controllers\Api\FacultyController.php',
    r'c:\Users\Hero\OneDrive\Desktop\CAPSTONE FINAL\backend\app\Http\Controllers\Api\CoordinatorController.php'
]

for file_path in controllers:
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Replace User::where('role', 'student') -> User::inDepartment()->where('role', 'student')
    content = re.sub(r'User::where\(\'role\',\s*\'student\'\)', r"User::inDepartment()->where('role', 'student')", content)
    
    # Replace Internship:: -> Internship::inDepartment()->
    # Wait, need to be careful with Internship::where, Internship::with, Internship::query, Internship::findOrFail
    content = re.sub(r'Internship::where\(', r'Internship::inDepartment()->where(', content)
    content = re.sub(r'Internship::whereIn\(', r'Internship::inDepartment()->whereIn(', content)
    content = re.sub(r'Internship::with\(', r'Internship::inDepartment()->with(', content)
    content = re.sub(r'Internship::query\(\)', r'Internship::inDepartment()', content)
    
    # Internship::findOrFail is trickier, it becomes Internship::inDepartment()->findOrFail
    content = re.sub(r'Internship::findOrFail\(', r'Internship::inDepartment()->findOrFail(', content)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

print("Controllers updated with scopeInDepartment()")
