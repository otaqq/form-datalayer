{{-- The settings screen of form-datalayer: containers and the forms of one tab. Publish the views (--tag=form-datalayer-views) to put them in your layout. --}}
<!doctype html>
<html lang="en">
<head>
@include('form-datalayer::_head')
<title>Form measurement</title>
</head>
<body>
<h1>Form measurement</h1>
@include('form-datalayer::_messages')

<form method="post" action="{{ route($routeName.'update') }}">
    @csrf
    @method('put')
    <input type="hidden" name="version" value="{{ $version }}">
    <input type="hidden" name="group" value="{{ $group }}">
    @php($oldContainers = old('containers'))
    @php($oldEnabled = old('enabled'))

    @if ($containers !== [])
        <fieldset data-test="containers">
            <legend>Google Tag Manager</legend>
            @foreach ($containers as $container)
                @if ($container['follows'] === null)
                    <label>{{ $container['label'] }}
                        <input type="text" name="containers[{{ $container['name'] }}]" value="{{ is_array($oldContainers) ? ($oldContainers[$container['name']] ?? '') : $container['id'] }}" placeholder="GTM-XXXXXXX" pattern="GTM-[A-Z0-9]+">
                    </label><br>
                @else
                    <input type="hidden" name="containers[{{ $container['name'] }}]" value="0">
                    <label><input type="checkbox" name="containers[{{ $container['name'] }}]" value="1" @checked(is_array($oldContainers) ? ($oldContainers[$container['name']] ?? '0') === '1' : $container['checked'])> {{ $container['label'] }}: the same container</label><br>
                @endif
            @endforeach
        </fieldset>
    @endif

    <nav class="tabs">
        @foreach ($groups as $name => $label)
            <a href="{{ route($routeName.'edit', ['group' => $name]) }}" @class(['active' => $name === $group]) data-group="{{ $name }}">{{ $label }}</a>
        @endforeach
    </nav>
    <table>
        <thead><tr><th>On</th><th>Form</th><th>Steps</th><th>Parameters sent</th><th></th></tr></thead>
        <tbody>
        @foreach ($forms as $row)
            <tr data-form="{{ $row['key'] }}">
                <td>
                    @if ($row['locked'])
                        <span class="note">Off by the app</span>
                    @else
                        <input type="hidden" name="enabled[{{ $row['key'] }}]" value="0">
                        <input type="checkbox" name="enabled[{{ $row['key'] }}]" value="1" @checked(is_array($oldEnabled) ? ($oldEnabled[$row['key']] ?? '0') === '1' : $row['enabled'])>
                    @endif
                </td>
                <td>{{ $row['label'] }} <span class="note">{{ $row['key'] }}</span>
                    @if ($row['new'])<span class="mark new">new</span>@elseif ($row['new_params'])<span class="mark new">new parameters</span>@endif
                </td>
                <td>{{ $row['steps'] }}</td>
                <td>{{ $row['params'] }}</td>
                <td><a href="{{ route($routeName.'form', $row['key']) }}">Details</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="note">Version {{ $version }}@if ($updatedAt), saved {{ $updatedAt }}@endif @if ($updatedBy) by {{ $updatedBy }}@endif</p>
    <button type="submit">Save</button>
</form>
</body>
</html>
