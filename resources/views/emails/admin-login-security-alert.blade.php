<x-mail::message>
{{ $badge }}

Your Admin account was recently used to access the POS and Inventory Management System.

**Access Details**

Admin: {{ $adminName }}
Date: {{ $date }}
Time: {{ $time }}
IP Address: {{ $ipAddress }}
Device/Browser: {{ $device }}

**Was this you?**

If you recognize this activity, no further action is required. If you did not authorize this access, please secure your account immediately.

<x-mail::button :url="$confirmUrl" color="success">
Yes, This Was Me
</x-mail::button>

<x-mail::button :url="$denyUrl" color="error">
No, This Was Not Me
</x-mail::button>

If a button above doesn't work, you can also review this login from within the system after logging in.
</x-mail::message>
