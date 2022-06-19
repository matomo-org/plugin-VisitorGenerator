<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <ContentBlock
    :content-title="translate('VisitorGenerator_VisitorGenerator')"
  >
    {{ translate('VisitorGenerator_GeneratedVisitsFor', siteName, days) }}<br/>
    {{ translate('VisitorGenerator_NumberOfGeneratedActions') }}: {{ nbActionsTotal }}<br/>
    {{ translate('VisitorGenerator_NbRequestsPerSec') }}: {{ nbRequestsPerSec }}<br/>
    {{ timer }}<br/>
    <p><strong>
      <span v-if="browserArchivingEnabled">
        {{ translate('VisitorGenerator_AutomaticReprocess') }}
      </span>
      <span v-else v-html="$sanitize(reRunArchiveScriptText)"></span>
    </strong>
    </p>
  </ContentBlock>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { translate, ContentBlock } from 'CoreHome';

export default defineComponent({
  props: {
    siteName: {
      type: String,
      required: true,
    },
    days: {
      type: [String, Number],
      required: true,
    },
    nbActionsTotal: {
      type: [String, Number],
      required: true,
    },
    nbRequestsPerSec: {
      type: [String, Number],
      required: true,
    },
    browserArchivingEnabled: Boolean,
    timer: {
      type: String,
      required: true,
    },
  },
  components: {
    ContentBlock,
  },
  computed: {
    reRunArchiveScriptText() {
      return translate(
        'VisitorGenerator_ReRunArchiveScript',
        '<a href="https://matomo.org/docs/setup-auto-archiving/">',
        '</a>',
      );
    },
  },
});
</script>
