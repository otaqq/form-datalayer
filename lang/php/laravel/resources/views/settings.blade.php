{{-- The settings screen of form-datalayer. Publish it (vendor:publish --tag=form-datalayer-views) to put it in your layout. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Form measurement</title>
<style>
body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 64rem; padding: 0 1rem; color: #222; }
fieldset { border: 1px solid #ccc; margin: 0 0 1.5rem; padding: 1rem; }
legend { font-weight: bold; }
table { border-collapse: collapse; width: 100%; }
th, td { border-top: 1px solid #eee; padding: .4rem; text-align: left; vertical-align: top; }
.note { color: #666; font-size: .9em; }
.mark { border-radius: .3em; font-size: .8em; padding: 0 .4em; }
.new { background: #e6f4ea; color: #137333; }
.error, .removed { background: #fce8e6; color: #a50e0e; padding: .5rem 1rem; }
.saved { background: #e6f4ea; padding: .5rem 1rem; }
</style>
</head>
<body>
<h1>Form measurement</h1>

@if (session('form-datalayer.saved'))
    <p class="saved" data-test="saved">Saved.</p>
@endif
@if (session('form-datalayer.conflict'))
    <p class="error" data-test="conflict">Someone else saved these settings first. Their settings are shown below with what you typed; check and save again.</p>
@endif
@if ($errors->has('form-datalayer'))
    <ul class="error" data-test="errors">
        @foreach ($errors->get('form-datalayer') as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
@if ($broken)
    <p class="error" data-test="broken">The saved settings cannot be read, so nothing is measured: {{ $broken }}. Saving replaces them.</p>
@endif
@if ($removed !== [])
    <div class="removed" data-test="removed">
        <p>No longer in the catalog (dropped on the next save):</p>
        <ul>
            @foreach ($removed as $name)
                <li>{{ $name }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="post" action="{{ route($routeName.'update') }}">
    @csrf
    @method('put')
    <input type="hidden" name="version" value="{{ $version }}">
    {{-- Form keys have dots, so old input is read as an array, not with dot notation --}}
    @php($old = old('forms'))

    @foreach ($forms as $form)
        @php($base = "forms[{$form['key']}]")
        @php($oldForm = is_array($old) ? ($old[$form['key']] ?? []) : null)
        <fieldset data-form="{{ $form['key'] }}">
            <legend>{{ $form['label'] }} <span class="note">{{ $form['key'] }}</span></legend>
            @if ($form['locked'])
                <p class="note">Turned off by the app.</p>
            @else
                <input type="hidden" name="{{ $base }}[enabled]" value="0">
                <label><input type="checkbox" name="{{ $base }}[enabled]" value="1" @checked($oldForm === null ? $form['enabled'] : ($oldForm['enabled'] ?? '0') === '1')> Measure</label>
                <table>
                    <thead><tr><th>Step</th><th>Event</th><th>Parameters</th></tr></thead>
                    <tbody>
                    @foreach ($form['steps'] as $step)
                        @php($stepBase = "{$base}[steps][{$step['step']}]")
                        @php($oldStep = $oldForm === null ? null : ($oldForm['steps'][$step['step']] ?? []))
                        <tr>
                            <td>{{ $step['step'] }}</td>
                            <td><input type="text" name="{{ $stepBase }}[event]" value="{{ $oldStep === null ? $step['event'] : ($oldStep['event'] ?? '') }}" placeholder="{{ $step['default_event'] }}" maxlength="40" pattern="[A-Za-z][A-Za-z0-9_]*"></td>
                            <td>
                                @forelse ($step['params'] as $param)
                                    <input type="hidden" name="{{ $stepBase }}[params][{{ $param['name'] }}]" value="0">
                                    <label>
                                        <input type="checkbox" name="{{ $stepBase }}[params][{{ $param['name'] }}]" value="1" @checked($oldStep === null ? $param['checked'] : ($oldStep['params'][$param['name']] ?? '0') === '1')>
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
            @endif
        </fieldset>
    @endforeach

    <p class="note">
        Version {{ $version }}@if ($updatedAt), saved {{ $updatedAt }}@endif @if ($updatedBy) by {{ $updatedBy }}@endif
    </p>
    <button type="submit">Save</button>
</form>
</body>
</html>
