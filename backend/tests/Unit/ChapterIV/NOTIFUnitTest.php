<?php
namespace Tests\Unit\ChapterIV;
use Tests\Support\IsolatedTestCase;
use App\Models\{User,Internship,AttendanceLog};
use App\Support\{InternshipAccess,InternshipStatuses,ManilaAttendanceClock,ManilaTime};
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator,Schema};
use Carbon\Carbon;

class NOTIFUnitTest extends IsolatedTestCase
{
    public function test_UT_NOTIF_02_U(): void
    {
        $this->assertSame('directMessages',\App\Support\NotificationPreferences::prefKeyForType('new_message')); $this->assertSame('meetingInvites',\App\Support\NotificationPreferences::prefKeyForType('meeting_invite'));
    }

    public function test_UT_NOTIF_PREF(): void
    {
        $u=new User(['role'=>'student','notification_preferences'=>['directMessages'=>false]]); $this->assertFalse($u->wantsNotification('directMessages')); $this->assertTrue($u->wantsNotification('attendanceAlerts'));
    }
}

