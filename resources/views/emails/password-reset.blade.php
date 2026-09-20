<x-mail::message>
# Reset your password

Hi **{{ $user->name }}**,

We received a request to reset the password for your Photo Backup Organizer account.

Click the button below to choose a new password. This link is valid for 60 minutes.

<x-mail::button :url="$resetUrl">
Reset my password
</x-mail::button>

If you didn't request this, you can safely ignore this email — your password will not change.

Thanks,<br>
The Photo Backup Organizer team
</x-mail::message>