(function(global, factory) {
  typeof exports === "object" && typeof module !== "undefined" ? factory(exports, require("vue"), require("CoreHome"), require("CorePluginsAdmin")) : typeof define === "function" && define.amd ? define(["exports", "vue", "CoreHome", "CorePluginsAdmin"], factory) : (global = typeof globalThis !== "undefined" ? globalThis : global || self, factory(global.VisitorGenerator = {}, global.Vue, global.CoreHome, global.CorePluginsAdmin));
})(this, (function(exports2, vue, CoreHome, CorePluginsAdmin) {
  "use strict";var __defProp = Object.defineProperty;
var __defProps = Object.defineProperties;
var __getOwnPropDescs = Object.getOwnPropertyDescriptors;
var __getOwnPropSymbols = Object.getOwnPropertySymbols;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __propIsEnum = Object.prototype.propertyIsEnumerable;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __spreadValues = (a, b) => {
  for (var prop in b || (b = {}))
    if (__hasOwnProp.call(b, prop))
      __defNormalProp(a, prop, b[prop]);
  if (__getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(b)) {
      if (__propIsEnum.call(b, prop))
        __defNormalProp(a, prop, b[prop]);
    }
  return a;
};
var __spreadProps = (a, b) => __defProps(a, __getOwnPropDescs(b));

  const _sfc_main$1 = vue.defineComponent({
    props: {
      accessLogPath: {
        type: String,
        required: true
      },
      idSite: {
        type: [String, Number],
        required: true
      },
      countMinActionsPerRun: {
        type: [String, Number],
        required: true
      },
      formNonce: {
        type: String,
        required: true
      }
    },
    components: {
      ContentBlock: CoreHome.ContentBlock,
      Field: CorePluginsAdmin.Field,
      Alert: CoreHome.Alert
    },
    data() {
      return {
        daysToCompute: "1",
        choice: false
      };
    },
    computed: {
      cliToolUsageText() {
        return CoreHome.translate(
          "VisitorGenerator_CliToolUsage",
          CoreHome.externalLink("http://developer.matomo.org/guides/piwik-on-the-command-line"),
          "</a>"
        );
      },
      generateLink() {
        return `?${CoreHome.MatomoUrl.stringify(__spreadProps(__spreadValues({}, CoreHome.MatomoUrl.urlParsed.value), {
          module: "VisitorGenerator",
          action: "generate"
        }))}`;
      },
      logImporterNoteText() {
        return CoreHome.translate(
          "VisitorGenerator_LogImporterNote",
          CoreHome.externalLink("https://plugins.matomo.org/VisitorGenerator"),
          "</a>"
        );
      }
    }
  });
  const _export_sfc = (sfc, props) => {
    const target = sfc.__vccOpts || sfc;
    for (const [key, val] of props) {
      target[key] = val;
    }
    return target;
  };
  const _hoisted_1$1 = ["innerHTML"];
  const _hoisted_2$1 = ["action"];
  const _hoisted_3 = ["value"];
  const _hoisted_4 = ["innerHTML"];
  const _hoisted_5 = ["value"];
  const _hoisted_6 = ["innerHTML"];
  const _hoisted_7 = ["value"];
  function _sfc_render$1(_ctx, _cache, $props, $setup, $data, $options) {
    const _component_Alert = vue.resolveComponent("Alert");
    const _component_Field = vue.resolveComponent("Field");
    const _component_ContentBlock = vue.resolveComponent("ContentBlock");
    return vue.openBlock(), vue.createBlock(_component_ContentBlock, {
      "content-title": _ctx.translate("VisitorGenerator_VisitorGenerator")
    }, {
      default: vue.withCtx(() => [
        vue.createElementVNode("p", null, vue.toDisplayString(_ctx.translate("VisitorGenerator_PluginDescription")), 1),
        vue.createVNode(_component_Alert, { severity: "info" }, {
          default: vue.withCtx(() => [
            vue.createElementVNode("span", {
              innerHTML: _ctx.$sanitize(_ctx.cliToolUsageText)
            }, null, 8, _hoisted_1$1),
            vue.createTextVNode(" " + vue.toDisplayString(_ctx.translate("VisitorGenerator_OverwriteLogFiles", _ctx.accessLogPath)), 1)
          ]),
          _: 1
        }),
        vue.createElementVNode("form", {
          method: "POST",
          action: _ctx.generateLink
        }, [
          vue.createElementVNode("input", {
            type: "hidden",
            name: "idSite",
            value: _ctx.idSite
          }, null, 8, _hoisted_3),
          vue.createVNode(_component_Field, {
            uicontrol: "text",
            name: "daysToCompute",
            modelValue: _ctx.daysToCompute,
            "onUpdate:modelValue": _cache[0] || (_cache[0] = ($event) => _ctx.daysToCompute = $event),
            title: _ctx.translate("VisitorGenerator_DaysToCompute")
          }, null, 8, ["modelValue", "title"]),
          vue.createElementVNode("p", null, [
            vue.createElementVNode("strong", null, vue.toDisplayString(_ctx.translate("VisitorGenerator_GenerateFakeActions", String(_ctx.countMinActionsPerRun))), 1)
          ]),
          vue.createElementVNode("p", null, vue.toDisplayString(_ctx.translate("VisitorGenerator_AreYouSure")), 1),
          vue.createVNode(_component_Alert, { severity: "danger" }, {
            default: vue.withCtx(() => [
              vue.createTextVNode(vue.toDisplayString(_ctx.translate("VisitorGenerator_Warning")) + " ", 1),
              vue.createElementVNode("span", {
                innerHTML: _ctx.$sanitize(
                  _ctx.translate("VisitorGenerator_NotReversible", "<strong>", "</strong>")
                )
              }, null, 8, _hoisted_4)
            ]),
            _: 1
          }),
          vue.createVNode(_component_Field, {
            uicontrol: "checkbox",
            name: "choice",
            modelValue: _ctx.choice,
            "onUpdate:modelValue": _cache[1] || (_cache[1] = ($event) => _ctx.choice = $event),
            title: _ctx.translate("VisitorGenerator_ChoiceYes")
          }, null, 8, ["modelValue", "title"]),
          vue.createElementVNode("input", {
            type: "hidden",
            value: _ctx.formNonce,
            name: "form_nonce"
          }, null, 8, _hoisted_5),
          vue.createElementVNode("p", null, [
            vue.createTextVNode(vue.toDisplayString(_ctx.translate("VisitorGenerator_PleaseBePatient")), 1),
            _cache[2] || (_cache[2] = vue.createElementVNode("br", null, null, -1)),
            vue.createElementVNode("span", {
              innerHTML: _ctx.$sanitize(_ctx.logImporterNoteText)
            }, null, 8, _hoisted_6)
          ]),
          vue.createElementVNode("input", {
            type: "submit",
            value: _ctx.translate("VisitorGenerator_Submit"),
            name: "submit",
            class: "btn"
          }, null, 8, _hoisted_7)
        ], 8, _hoisted_2$1)
      ]),
      _: 1
    }, 8, ["content-title"]);
  }
  const AdminPage = /* @__PURE__ */ _export_sfc(_sfc_main$1, [["render", _sfc_render$1]]);
  const _sfc_main = vue.defineComponent({
    props: {
      siteName: {
        type: String,
        required: true
      },
      days: {
        type: [String, Number],
        required: true
      },
      nbActionsTotal: {
        type: [String, Number],
        required: true
      },
      nbRequestsPerSec: {
        type: [String, Number],
        required: true
      },
      browserArchivingEnabled: Boolean,
      timer: {
        type: String,
        required: true
      }
    },
    components: {
      ContentBlock: CoreHome.ContentBlock
    },
    computed: {
      reRunArchiveScriptText() {
        return CoreHome.translate(
          "VisitorGenerator_ReRunArchiveScript",
          CoreHome.externalLink("https://matomo.org/docs/setup-auto-archiving/"),
          "</a>"
        );
      }
    }
  });
  const _hoisted_1 = { key: 0 };
  const _hoisted_2 = ["innerHTML"];
  function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
    const _component_ContentBlock = vue.resolveComponent("ContentBlock");
    return vue.openBlock(), vue.createBlock(_component_ContentBlock, {
      "content-title": _ctx.translate("VisitorGenerator_VisitorGenerator")
    }, {
      default: vue.withCtx(() => [
        vue.createTextVNode(vue.toDisplayString(_ctx.translate("VisitorGenerator_GeneratedVisitsFor", _ctx.siteName, String(_ctx.days))), 1),
        _cache[0] || (_cache[0] = vue.createElementVNode("br", null, null, -1)),
        vue.createTextVNode(" " + vue.toDisplayString(_ctx.translate("VisitorGenerator_NumberOfGeneratedActions")) + ": " + vue.toDisplayString(_ctx.nbActionsTotal), 1),
        _cache[1] || (_cache[1] = vue.createElementVNode("br", null, null, -1)),
        vue.createTextVNode(" " + vue.toDisplayString(_ctx.translate("VisitorGenerator_NbRequestsPerSec")) + ": " + vue.toDisplayString(_ctx.nbRequestsPerSec), 1),
        _cache[2] || (_cache[2] = vue.createElementVNode("br", null, null, -1)),
        vue.createTextVNode(" " + vue.toDisplayString(_ctx.timer), 1),
        _cache[3] || (_cache[3] = vue.createElementVNode("br", null, null, -1)),
        vue.createElementVNode("p", null, [
          vue.createElementVNode("strong", null, [
            _ctx.browserArchivingEnabled ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_1, vue.toDisplayString(_ctx.translate("VisitorGenerator_AutomaticReprocess")), 1)) : (vue.openBlock(), vue.createElementBlock("span", {
              key: 1,
              innerHTML: _ctx.$sanitize(_ctx.reRunArchiveScriptText)
            }, null, 8, _hoisted_2))
          ])
        ])
      ]),
      _: 1
    }, 8, ["content-title"]);
  }
  const GeneratePage = /* @__PURE__ */ _export_sfc(_sfc_main, [["render", _sfc_render]]);
  exports2.AdminPage = AdminPage;
  exports2.GeneratePage = GeneratePage;
  Object.defineProperty(exports2, Symbol.toStringTag, { value: "Module" });
}));
