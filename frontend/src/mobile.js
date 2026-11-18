import { Capacitor } from "@capacitor/core";
import { App } from "@capacitor/app";
import { Network } from "@capacitor/network";
import { SplashScreen } from "@capacitor/splash-screen";
if (Capacitor.isNativePlatform()) {
  Network.addListener("networkStatusChange", (status) => {
    window.dispatchEvent(new Event(status.connected ? "online" : "offline"));
  });
  App.addListener("backButton", () => {
    if (location.hash && location.hash !== "#dashboard")
      location.hash = "dashboard";
    else App.minimizeApp();
  });
  SplashScreen.hide().catch(() => {});
}
