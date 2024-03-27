<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <ContentBlock :content-title="translate('VisitorGenerator_VisitorGenerator')">
    <p>{{ translate('VisitorGenerator_PluginDescription') }}</p>

    <Alert severity="info">
      <span v-html="$sanitize(cliToolUsageText)"></span>
      {{ translate('VisitorGenerator_OverwriteLogFiles', accessLogPath) }}
    </Alert>

    <form method="POST" :action="generateLink">

      <input type="hidden" name="idSite" :value="idSite">

      <Field
        uicontrol="text"
        name="daysToCompute"
        v-model="daysToCompute"
        :title="translate('VisitorGenerator_DaysToCompute')"
      />

      <p>
        <strong>
          {{ translate('VisitorGenerator_GenerateFakeActions', countMinActionsPerRun) }}
        </strong>
      </p>

      <p>{{ translate('VisitorGenerator_AreYouSure') }}</p>

      <Alert severity="danger">
        {{ translate('VisitorGenerator_Warning') }}
        <span v-html="$sanitize(
          translate('VisitorGenerator_NotReversible', '<strong>', '</strong>'),
        )"></span>
      </Alert>

      <Field
        uicontrol="checkbox"
        name="choice"
        v-model="choice"
        :title="translate('VisitorGenerator_ChoiceYes')"
      />

      <input type="hidden" :value="formNonce" name="form_nonce"/>

      <p>
        {{ translate('VisitorGenerator_PleaseBePatient') }}<br/>
        <span v-html="$sanitize(logImporterNoteText)"></span>
      </p>

      <input
        type="submit"
        :value="translate('VisitorGenerator_Submit')"
        name="submit"
        class="btn"
      />
    </form>
  </ContentBlock>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import {
  translate,
  ContentBlock,
  Alert,
  MatomoUrl,
  externalLink,
} from 'CoreHome';
import { Field } from 'CorePluginsAdmin';

interface AdminPageState {
  daysToCompute: string;
  choice: boolean;
}

export default defineComponent({
  props: {
    accessLogPath: {
      type: String,
      required: true,
    },
    idSite: {
      type: [String, Number],
      required: true,
    },
    countMinActionsPerRun: {
      type: [String, Number],
      required: true,
    },
    formNonce: {
      type: String,
      required: true,
    },
  },
  components: {
    ContentBlock,
    Field,
    Alert,
  },
  data(): AdminPageState {
    return {
      daysToCompute: '1',
      choice: false,
    };
  },
  computed: {
    cliToolUsageText() {
      return translate(
        'VisitorGenerator_CliToolUsage',
        externalLink('http://developer.matomo.org/guides/piwik-on-the-command-line'),
        '</a>',
      );
    },
    generateLink() {
      return `?${MatomoUrl.stringify({
        ...MatomoUrl.urlParsed.value,
        module: 'VisitorGenerator',
        action: 'generate',
      })}`;
    },
    logImporterNoteText() {
      return translate(
        'VisitorGenerator_LogImporterNote',
        externalLink('https://plugins.matomo.org/VisitorGenerator'),
        '</a>',
      );
    },
  },
});
</script>
