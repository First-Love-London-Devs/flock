@extends('layouts.public')

@section('title', 'Support')

@section('content')
    <h1 class="page-title">Support</h1>
    <p class="page-subtitle">We're here to help you get the most out of Church Stack.</p>

    <div class="card">
        <h3>Email Support</h3>
        <p>For general inquiries, account issues, or technical support, reach out to us at <a href="mailto:support@church-stack.com">support@church-stack.com</a>. We typically respond within 24 hours.</p>
    </div>

    <div class="card">
        <h3>Getting Started</h3>
        <p>New to Church Stack? Here are a few things to help you hit the ground running:</p>
        <ul style="padding-left: 1.25rem; list-style: disc; margin-top: 0.5rem;">
            <li style="color: #374151; font-size: 0.95rem; margin-bottom: 0.35rem;">Set up your church profile and configure group types (Zones, Districts, Cells)</li>
            <li style="color: #374151; font-size: 0.95rem; margin-bottom: 0.35rem;">Add members and assign them to groups</li>
            <li style="color: #374151; font-size: 0.95rem; margin-bottom: 0.35rem;">Create leader accounts and assign roles for access control</li>
            <li style="color: #374151; font-size: 0.95rem; margin-bottom: 0.35rem;">Start tracking attendance through the mobile app</li>
        </ul>
    </div>

    <div class="card">
        <h3>Frequently Asked Questions</h3>
        <p><strong>How do I add a new leader?</strong><br>
        Go to the Members section in the admin panel, find the member, and use the "Make Leader" action. You can assign a role during creation.</p>
        <br>
        <p><strong>How does attendance tracking work?</strong><br>
        Leaders can submit attendance for their groups through the mobile app. Select the group, date, and mark members as present or absent.</p>
        <br>
        <p><strong>Can I manage multiple branches?</strong><br>
        Yes. Church Stack supports multi-tenancy, so each branch operates with its own isolated data while being managed from a central admin panel.</p>
    </div>

    <div class="card">
        <h3>Report a Bug</h3>
        <p>Found something that doesn't look right? Let us know at <a href="mailto:support@church-stack.com">support@church-stack.com</a> with a description of the issue and any screenshots if possible.</p>
    </div>
@endsection
