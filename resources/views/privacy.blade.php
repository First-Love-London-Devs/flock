@extends('layouts.public')

@section('title', 'Privacy Policy')

@section('content')
    <h1 class="page-title">Privacy Policy</h1>
    <p class="page-subtitle">Last updated: {{ date('F j, Y') }}</p>

    <div class="section">
        <h2>Overview</h2>
        <p>Church Stack ("we", "our", or "us") is committed to protecting the privacy of our users. This Privacy Policy explains how we collect, use, and safeguard your personal information when you use our platform and mobile application.</p>
    </div>

    <div class="section">
        <h2>Information We Collect</h2>
        <p>We collect the following types of information:</p>
        <ul>
            <li><strong>Account Information:</strong> Name, email address, phone number, and login credentials when you create an account.</li>
            <li><strong>Member Data:</strong> Names, contact details, dates of birth, gender, marital status, and other profile information entered by church administrators for church management purposes.</li>
            <li><strong>Attendance Records:</strong> Date, group, and attendance status submitted through the platform.</li>
            <li><strong>Usage Data:</strong> Information about how you interact with our platform, including device information and access logs.</li>
        </ul>
    </div>

    <div class="section">
        <h2>How We Use Your Information</h2>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Provide and maintain the Church Stack platform</li>
            <li>Enable church administrators to manage their members, groups, and attendance</li>
            <li>Send important notifications about your account or our services</li>
            <li>Improve and develop new features for the platform</li>
            <li>Ensure the security and integrity of our services</li>
        </ul>
    </div>

    <div class="section">
        <h2>Data Ownership & Multi-Tenancy</h2>
        <p>Each church on Church Stack operates within its own isolated environment. Church data is separated and not shared between organisations. Church administrators are responsible for the data they enter about their members and should ensure they have appropriate consent to store this information.</p>
    </div>

    <div class="section">
        <h2>Data Security</h2>
        <p>We implement appropriate technical and organisational measures to protect your personal information, including encryption of data in transit, secure authentication, and role-based access controls. However, no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.</p>
    </div>

    <div class="section">
        <h2>Data Retention</h2>
        <p>We retain your personal information for as long as your account is active or as needed to provide our services. Church administrators may delete member records at any time. When a church's account is terminated, all associated data is removed from our systems.</p>
    </div>

    <div class="section">
        <h2>Third-Party Services</h2>
        <p>We do not sell, trade, or otherwise transfer your personal information to third parties. We may use trusted third-party services for hosting, analytics, and push notifications, all of which are bound by their own privacy policies.</p>
    </div>

    <div class="section">
        <h2>Your Rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Access the personal information we hold about you</li>
            <li>Request correction of inaccurate information</li>
            <li>Request deletion of your personal data</li>
            <li>Withdraw consent for data processing where applicable</li>
        </ul>
        <p style="margin-top: 0.5rem;">To exercise any of these rights, please contact your church administrator or reach out to us directly.</p>
    </div>

    <div class="section">
        <h2>Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will notify you of any significant changes by posting the new policy on this page and updating the "Last updated" date.</p>
    </div>

    <div class="section">
        <h2>Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please contact us at <a href="mailto:support@church-stack.com">support@church-stack.com</a>.</p>
    </div>
@endsection
