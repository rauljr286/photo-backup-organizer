<p>
            This app collects and stores the photos you upload, along with basic account information
            (name, email) needed to provide backup and login services.
        </p>

        <h2>How we handle your photos</h2>
        <ul>
            <li>Your photos are private and are not shared with other users or third parties without your explicit action (e.g., using a share feature).</li>
            <li>Photos are stored securely and encrypted in transit and at rest.</li>
            <li>We do not sell or use your photos for advertising or AI training purposes.</li>
            <li>You may delete your account and all associated photos at any time from
                <a class="link" href="{{ route('settings', ['tab' => 'privacy']) }}">Settings</a>.</li>
            <li>
                We retain deleted items for <strong>{{ $retentionDays }} days</strong> in a recovery trash
                before permanent removal.
            </li>
            <li>Contact <a class="link" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a> for
                privacy-related questions or data deletion requests.</li>
        </ul>

        <h2>What we collect</h2>
        <ul>
            <li><strong>Account information:</strong> your name, email address and a securely hashed password.</li>
            <li><strong>Your photos:</strong> the image files you upload, along with metadata we store for
                organisation, such as capture dates and any descriptions or tags you add.</li>
            <li><strong>Technical data:</strong> anonymous usage data needed to keep the service running and secure.</li>
        </ul>

        <h2>How we store data</h2>
        <p>
            Photos are stored in a private storage area owned by your account. Files are transferred over
            encrypted connections (TLS) and kept encrypted at rest. Access is authenticated and isolated so
            that one user can never read another user&rsquo;s photos.
        </p>

        <h2>Your rights</h2>
        <ul>
            <li>Export or download your photos at any time.</li>
            <li>Delete individual photos, albums, or your entire account.</li>
            <li>Request a copy or deletion of your personal data by emailing
                <a class="link" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</li>
        </ul>