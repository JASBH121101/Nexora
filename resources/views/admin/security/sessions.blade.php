@extends('layouts.app')

@section('title', __('security.sessions_title').' · NEXORA')

@section('content')
<section class="panel sessions-panel">
    <p class="muted eyebrow">NEXORA ADMIN · SEGURIDAD</p>
    <h1>{{ __('security.sessions_title') }}</h1>
    <p class="muted">{{ __('security.sessions_subtitle') }}</p>

    @if (session('status'))
        <div class="notice success" role="status">{{ session('status') }}</div>
    @endif

    @if ($sessions->isEmpty())
        <div class="empty-state">{{ __('security.sessions_empty') }}</div>
    @else
        <div class="session-list">
            @foreach ($sessions as $activeSession)
                <article class="session-card">
                    <div class="session-heading">
                        <div>
                            <h2>{{ $activeSession->user?->name ?? __('security.unknown_user') }}</h2>
                            <p class="muted">{{ $activeSession->user?->username }} · {{ ucfirst($activeSession->user?->role ?? '') }}</p>
                        </div>
                        @if (hash_equals($activeSession->session_hash, $currentSessionHash))
                            <span class="current-label">{{ __('security.current_session') }}</span>
                        @endif
                    </div>
                    <dl>
                        <div><dt>{{ __('security.session_ip') }}</dt><dd>{{ $activeSession->ip_address ?: '—' }}</dd></div>
                        <div><dt>{{ __('security.session_last_activity') }}</dt><dd>{{ \Illuminate\Support\Carbon::createFromTimestamp($activeSession->last_activity)->diffForHumans() }}</dd></div>
                        <div class="agent"><dt>{{ __('security.session_device') }}</dt><dd>{{ $activeSession->user_agent ?: __('security.unknown_device') }}</dd></div>
                    </dl>
                    <form method="POST" action="{{ route('admin.security.sessions.destroy', $activeSession->id) }}" onsubmit="return confirm(@js(__('security.session_revoke_confirm')))">
                        @csrf
                        @method('DELETE')
                        <button class="revoke-button" type="submit">
                            {{ hash_equals($activeSession->session_hash, $currentSessionHash) ? __('security.end_current_session') : __('security.revoke_session') }}
                        </button>
                    </form>
                </article>
            @endforeach
        </div>

        <div class="pagination">{{ $sessions->links() }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.home') }}">{{ __('security.back') }}</a>
</section>
@endsection

@push('head')
<style>
    .sessions-panel{max-width:760px;margin:30px auto 0}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em}.session-list{display:grid;gap:12px;margin:22px 0}.session-card{padding:17px;border:1px solid #e4e9df;border-radius:12px;background:#fff}.session-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.session-heading h2{margin:0;font-size:16px}.session-heading p{margin:5px 0 0;font-size:12px}.current-label{flex:none;padding:5px 9px;border-radius:20px;background:#edf5e8;color:#42622c;font-size:11px;font-weight:800}.session-card dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:16px 0}.session-card dl div{min-width:0}.session-card dt{color:#737d6e;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.session-card dd{margin:5px 0 0;font-size:12px;overflow-wrap:anywhere}.session-card .agent{grid-column:1/-1}.revoke-button{min-height:38px;padding:0 12px;border:1px solid #ead4d0;border-radius:9px;background:#fff5f3;color:#963b32;font:inherit;font-size:12px;font-weight:800;cursor:pointer}.empty-state{margin:20px 0;padding:18px;border:1px dashed #dce3d5;border-radius:11px;color:#697366;font-size:13px}.pagination{margin:18px 0;font-size:13px}.back-link{display:inline-block;margin-top:12px;color:#56614d;font-size:13px;font-weight:700}.notice{margin:12px 0;padding:12px 14px;border-radius:10px;font-size:13px}.success{background:#edf7ed;color:#286536}@media(max-width:560px){.sessions-panel{padding:17px}.session-heading{flex-direction:column}.session-card dl{grid-template-columns:1fr}.session-card .agent{grid-column:auto}}
</style>
@endpush
