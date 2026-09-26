<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class PORTUnitTest extends IsolatedTestCase
{
    public function test_UT_PORT_11_U(): void
    {
        $this->assertSame(42,InternshipAccess::internshipIdFromPath('portfolios/42/logo.png')); $this->assertNull(InternshipAccess::internshipIdFromPath('unknown/logo.png'));
    }
}

