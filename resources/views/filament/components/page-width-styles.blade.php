{{-- Global width of record cards (edit/view pages of every resource: order, lead, client, installer ...):
     about 80% of the screen, but never narrower than 900px (or the screen, if smaller) and never
     wider than 1500px; aligned to the left. Edit the three numbers here once. --}}
<style>
    :root {
        --card-page-min: 900px;
        --card-page-width: 80%;
        --card-page-max: 1500px;
        --client-page-max: 1200px;  /* client card: ONE width for all tabs (form and tables), centred */
    }

    .fi-resource-edit-record-page,
    .fi-resource-view-record-page {
        width: min(100%, clamp(var(--card-page-min), var(--card-page-width), var(--card-page-max)));
        margin-inline: auto;
    }

    .fi-resource-clients.fi-resource-edit-record-page,
    .fi-resource-clients.fi-resource-view-record-page {
        width: min(100%, var(--client-page-max));
    }

</style>
