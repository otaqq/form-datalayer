{{-- One form of the settings screen of form-datalayer: on / off, event names and parameters by step. --}}
<!doctype html>
<html lang="en">
<head>
@include('form-datalayer::_head')
<title>{{ $form['label'] }} - Form measurement</title>
</head>
<body>
<p><a href="{{ route($routeName.'edit', ['group' => $form['group']]) }}">{{ $groupLabel }}</a></p>
<h1>{{ $form['label'] }} <span class="note">{{ $form['key'] }}</span></h1>
@include('form-datalayer::_messages')

@if ($form['locked'])
    <p class="note">Turned off by the app.</p>
@else
    {{-- Form keys have dots, so old input is read as an array, not with dot notation --}}
    @php($old = old('steps'))
    <form method="post" action="{{ route($routeName.'form.update', $form['key']) }}">
        @csrf
        @method('put')
        <input type="hidden" name="version" value="{{ $version }}">
        <input type="hidden" name="enabled" value="0">
        <label><input type="checkbox" name="enabled" value="1" @checked(old('enabled') !== null ? old('enabled') === '1' : $form['enabled'])> Measure (push every step)</label>
        <table>
            <thead><tr><th>Step</th><th>Event</th><th>Parameters</th></tr></thead>
            <tbody>
            @foreach ($form['steps'] as $step)
                @php($base = "steps[{$step['step']}]")
                @php($oldStep = is_array($old) ? ($old[$step['step']] ?? []) : null)
                <tr>
                    <td>{{ $step['step'] }}</td>
                    <td><input type="text" name="{{ $base }}[event]" value="{{ $oldStep === null ? $step['event'] : ($oldStep['event'] ?? '') }}" placeholder="{{ $step['default_event'] }}" maxlength="40" pattern="[A-Za-z][A-Za-z0-9_]*"></td>
                    <td>
                        @forelse ($step['params'] as $param)
                            <input type="hidden" name="{{ $base }}[params][{{ $param['name'] }}]" value="0">
                            <label>
                                <input type="checkbox" name="{{ $base }}[params][{{ $param['name'] }}]" value="1" @checked($oldStep === null ? $param['checked'] : ($oldStep['params'][$param['name']] ?? '0') === '1')>
                                {{ $param['label'] }} <span class="note">{{ $param['name'] }}</span>
                                @if ($param['new'])<span class="mark new">new</span>@endif
                            </label><br>
                        @empty
                            <span class="note">None in the catalog</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <button type="submit">Save</button>
    </form>
@endif
</body>
</html>
