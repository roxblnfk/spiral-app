import {Event} from "./Event"

export default class extends Event {
  labels = ['exception']
  color = 'pink'
  app = 'sentry'

  constructor(event, id, timestamp) {
    super(event, id, timestamp)

    const def = {
      type: 'Unknown',
      value: 'Something went wrong',
      stacktrace: {
        frames: []
      }
    }

    this._payload = event.exception ? event.exception.values[0] || def : def

    this._stacktrace = this._payload.stacktrace.frames.reverse()
    this._contexts = event.contexts || {
      os: {},
      runtime: {}
    }
  }

  get route() {
    return {
      index: `/${this.app}`,
      show: `/${this.app}/${this.id}`,
      json: `/api/event/${this.id}`,
    }
  }

  get origin() {
    return {
      logger: this.logger,
      env: this.environment
    }
  }

  get serverName() {
    return this.event.server_name
  }

  get type() {
    return 'Sentry'
  }

  get payload() {
    return this._payload
  }

  get request() {
    return this.event.request
  }

  get platform() {
    return this.event.platform
  }

  get logger() {
    return this.event.logger
  }

  get sdk() {
    return this.event.sdk
  }

  get os() {
    return this._contexts.os
  }

  get environment() {
    return this.event.environment
  }

  get runtime() {
    return this._contexts.runtime
  }

  get stacktrace() {
    return this._stacktrace
  }

  get tags() {
    return this.event.tags
  }

  get exceptions() {
    if (!this.event.exception) {
      return []
    }

    return this.event.exception.values || []
  }

  get breadcrumbs() {
    if (!this.event.breadcrumbs) {
      return []
    }

    return this.event.breadcrumbs.values || []
  }


  get contexts() {
    return this._contexts
  }

  get user() {
    return this.event.user
  }

  get location() {
    const lastElm = [this.stacktrace.length - 1];
    if (lastElm < 0) {
      return null
    }

    return this.stacktrace[lastElm]
  }
}
